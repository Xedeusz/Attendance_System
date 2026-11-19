<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceHolidayRequest;
use App\Models\AttendanceHoliday;
use App\Models\Branch;
use App\Models\HolidayImport;
use App\Services\Administration;
use App\Services\AttendancePolicyResolver;
use App\Services\AuditService;
use App\Services\HolidayCsvImport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AttendanceHolidayController extends Controller
{
    public function __construct(private Administration $administration, private AuditService $audit) {}

    public function index(Request $request): View
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:1900,2200'], 'month' => ['nullable', 'integer', 'between:1,12'],
            'type' => ['nullable', Rule::in(array_keys(AttendanceHoliday::TYPES))], 'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'view' => ['nullable', Rule::in(['list', 'month'])],
        ]);
        $today = CarbonImmutable::today(config('attendance.timezone'));
        $year = (int) ($data['year'] ?? $today->year);
        $month = CarbonImmutable::create($year, (int) ($data['month'] ?? $today->month), 1, timezone: $today->timezone);
        $monthly = ($data['view'] ?? 'list') === 'month';
        $range = $monthly ? $month : $month->startOfYear();
        $branch = isset($data['branch_id']) ? Branch::find($data['branch_id']) : null;
        $holidays = AttendanceHoliday::with('branches')
            ->where('holiday_date', '>=', $range->toDateString())
            ->where('holiday_date', '<', ($monthly ? $range->endOfMonth() : $range->endOfYear())->addDay()->toDateString())
            ->when($data['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->orderBy('holiday_date')->orderBy('name')->get()
            ->filter(fn (AttendanceHoliday $holiday): bool => ! $branch || $holiday->appliesTo($branch->id, AttendancePolicyResolver::location($branch)));

        return view('attendance.policies.holidays', [
            'holidays' => $holidays, 'year' => $year, 'month' => $month, 'monthly' => $monthly,
            'branches' => Branch::orderBy('branch_name')->pluck('branch_name', 'id'),
        ]);
    }

    public function create(): View
    {
        return $this->form(new AttendanceHoliday);
    }

    public function edit(AttendanceHoliday $holiday): View
    {
        return $this->form($holiday->load('branches'));
    }

    public function show(AttendanceHoliday $holiday): View
    {
        $holiday->load('branches');

        return view('attendance.policies.holiday-show', compact('holiday'));
    }

    private function form(AttendanceHoliday $holiday): View
    {
        $branches = Branch::orderBy('branch_name')->get();

        return view('attendance.policies.holiday-form', [
            'holiday' => $holiday, 'branches' => $branches->pluck('branch_name', 'id'),
            'locations' => AttendancePolicyResolver::locationOptions($branches),
        ]);
    }

    public function store(AttendanceHolidayRequest $request): RedirectResponse
    {
        return $this->save($request, new AttendanceHoliday);
    }

    public function update(AttendanceHolidayRequest $request, AttendanceHoliday $holiday): RedirectResponse
    {
        return $this->save($request, $holiday);
    }

    private function save(AttendanceHolidayRequest $request, AttendanceHoliday $holiday): RedirectResponse
    {
        $data = $request->validated();
        $this->administration->run(function () use ($data, $holiday, $request): void {
            $before = $holiday->exists ? [...$holiday->refresh()->snapshot(), 'scope' => $holiday->scope,
                'status' => $holiday->status, 'locations' => $holiday->locations, 'branches' => $holiday->branches()->pluck('branches.id')->all()] : null;
            $branches = $data['branch_ids'] ?? [];
            $locations = $data['locations'] ?? null;

            $holiday->fill([
                ...Arr::except($data, ['branch_ids']), 'locations' => $locations,
                'identity_hash' => HolidayCsvImport::identity($data['holiday_date'], $data['name'], $data['scope'], $locations ?? $branches),
                'updated_by' => $request->user()->id,
            ]);
            if (! $holiday->exists) {
                $holiday->created_by = $request->user()->id;
            }
            $holiday->save();
            $holiday->branches()->sync($branches);
            $this->audit->record($before ? 'attendance.holiday_updated' : 'attendance.holiday_created', $holiday, [
                'previous' => $before,
                'current' => [...$holiday->snapshot(), 'scope' => $holiday->scope, 'status' => $holiday->status, 'locations' => $locations, 'branches' => $branches],
            ]);
        });

        return to_route('hr.holidays.show', $holiday)->with('status', 'Holiday saved.');
    }

    public function importForm(): View
    {
        return view('attendance.policies.holiday-import', ['history' => HolidayImport::latest('id')->paginate(15)]);
    }

    public function importPreview(Request $request, HolidayCsvImport $import): View|RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:1900,2200'], 'source_url' => ['required', 'url:https', 'max:2048'],
            'declaration' => ['required', 'string', 'min:5', 'max:255'], 'csv' => ['required', 'file', 'mimes:txt,csv', 'max:64'],
            'verified' => ['accepted'],
        ]);
        $host = strtolower((string) parse_url($data['source_url'], PHP_URL_HOST));
        if (! str_ends_with($host, '.gov.ph')) {
            throw ValidationException::withMessages(['source_url' => 'Use an official Philippine government HTTPS source on a gov.ph domain.']);
        }
        $rows = $import->read($data['csv'], (int) $data['year']);
        $statuses = $import->statuses($rows);
        $token = Crypt::encryptString(json_encode([
            'purpose' => 'holiday_import', 'user' => $request->user()->id, 'expires' => now()->addMinutes(20)->timestamp,
            'source' => Arr::only($data, ['year', 'source_url', 'declaration']), 'rows' => $rows, 'statuses' => $statuses,
        ], JSON_THROW_ON_ERROR));

        $request->session()->put('holiday_import_preview', $token);

        return $this->reviewImport($request);
    }

    public function reviewImport(Request $request): View|RedirectResponse
    {
        $token = $request->session()->get('holiday_import_preview');
        if (! $token) {
            return to_route('hr.holiday-import.index');
        }
        $preview = $this->openImportPreview($request, $token);

        return view('attendance.policies.holiday-import-preview', [
            'data' => $preview['source'], 'rows' => $preview['rows'], 'statuses' => $preview['statuses'], 'token' => $token,
        ]);
    }

    public function importConfirm(Request $request, HolidayCsvImport $import): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:524288'], 'selected' => ['required', 'array', 'list', 'max:200'],
            'selected.*' => ['required', 'integer', 'min:0', 'distinct'], 'confirm' => ['accepted'],
            'allow_conflicts' => ['sometimes', 'accepted'],
        ]);
        $preview = $this->openImportPreview($request, $data['token']);

        $this->administration->run(function () use ($data, $preview, $request, $import): void {
            $checks = $import->statuses($preview['rows']);
            $selected = array_map('intval', $data['selected']);
            sort($selected);
            $rows = [];
            foreach ($selected as $index) {
                if (! isset($checks[$index]) || $checks[$index] !== $preview['statuses'][$index] || $checks[$index]['duplicate']) {
                    throw ValidationException::withMessages(['csv' => 'A selection is invalid, duplicated, or changed. Generate a new preview.']);
                }
                if ($checks[$index]['conflict'] && empty($data['allow_conflicts'])) {
                    throw ValidationException::withMessages(['allow_conflicts' => 'Confirm the flagged overlapping declarations to proceed.']);
                }
                $rows[] = $preview['rows'][$index];
            }

            $actorId = $request->user()->id;
            $batch = HolidayImport::create([
                ...$preview['source'], 'approved_by' => $actorId, 'imported_count' => count($rows),
                'content_hash' => hash('sha256', json_encode([$preview['source'], $rows], JSON_THROW_ON_ERROR)),
            ]);
            foreach ($rows as $row) {
                AttendanceHoliday::create([
                    ...$row, ...Arr::only($preview['source'], ['source_url', 'declaration']),
                    'holiday_import_id' => $batch->id, 'status' => 'active', 'scope' => 'company',
                    'created_by' => $actorId, 'updated_by' => $actorId,
                    'identity_hash' => HolidayCsvImport::identity($row['holiday_date'], $row['name'], 'company'),
                ]);
            }
            $this->audit->record('attendance.holidays_imported', $batch, ['count' => count($rows), 'source' => $batch->source_url]);
        });

        $request->session()->forget('holiday_import_preview');

        return to_route('hr.holiday-import.index')->with('status', 'Approved holidays imported.');
    }

    private function openImportPreview(Request $request, string $token): array
    {
        try {
            $preview = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            abort(422, 'Reopen the import preview.');
        }
        abort_unless(($preview['purpose'] ?? null) === 'holiday_import' && $preview['user'] === $request->user()->id
            && $preview['expires'] >= now()->timestamp, 422, 'The import preview expired or does not belong to this account.');

        return $preview;
    }
}

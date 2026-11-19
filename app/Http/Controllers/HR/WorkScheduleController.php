<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkScheduleRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleException;
use App\Services\Administration;
use App\Services\AttendancePolicyResolver;
use App\Services\AuditService;
use App\Services\WorkDuration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkScheduleController extends Controller
{
    public function __construct(private Administration $administration, private AuditService $audit) {}

    public function index(): View
    {
        return view('attendance.policies.schedules', [
            'schedules' => WorkSchedule::with(['employee', 'department', 'branch'])->orderByDesc('id')->paginate(20),
            'exceptions' => WorkScheduleException::with(['employee', 'workSchedule'])->latest('id')->paginate(10, pageName: 'exception_page'),
            'employees' => $this->employees(),
            'shiftOptions' => WorkSchedule::where('active', true)->orderBy('name')->get()->mapWithKeys(fn (WorkSchedule $schedule) => [$schedule->id => $schedule->name.' (#'.$schedule->id.')']),
        ]);
    }

    public function create(): View
    {
        return $this->form(new WorkSchedule);
    }

    public function edit(WorkSchedule $schedule): View
    {
        return $this->form($schedule);
    }

    private function form(WorkSchedule $schedule): View
    {
        return view('attendance.policies.schedule-form', [
            'schedule' => $schedule,
            'employees' => $this->employees(),
            'departments' => Department::orderBy('department_name')->pluck('department_name', 'id'),
            'branches' => Branch::orderBy('branch_name')->pluck('branch_name', 'id'),
        ]);
    }

    private function employees(): array
    {
        return Employee::orderBy('last_name')->get(['id', 'employee_number', 'first_name', 'middle_name', 'last_name', 'suffix'])
            ->mapWithKeys(fn (Employee $employee) => [$employee->id => $employee->full_name.' ('.$employee->employee_number.')'])->all();
    }

    public function store(WorkScheduleRequest $request): RedirectResponse
    {
        $this->save($request);

        return to_route('hr.schedules.index')->with('status', 'Work schedule saved.');
    }

    public function update(WorkScheduleRequest $request, WorkSchedule $schedule): RedirectResponse
    {
        $this->save($request, $schedule);

        return to_route('hr.schedules.index')->with('status', 'New effective-dated schedule version saved.');
    }

    private function save(WorkScheduleRequest $request, ?WorkSchedule $previous = null): void
    {
        $data = $request->validated();
        $this->administration->run(function () use ($request, $previous, $data): void {
            $emptyScope = ['employee_id' => null, 'branch_id' => null, 'department_id' => null];
            $scopeKey = $data['scope'].'_id';
            $targets = $data['scope'] === 'company' ? [$emptyScope] : array_map(
                fn ($id): array => [...$emptyScope, $scopeKey => (int) $id],
                $data[$scopeKey.'s'] ?? [$data[$scopeKey]],
            );

            if ($previous) {
                $previous->refresh();
                $sameScope = count($targets) === 1 && $previous->only(array_keys($emptyScope)) == $targets[0];
                if (! $sameScope || $data['effective_from'] < $previous->effective_from->toDateString()
                    || $data['effective_from'] < now(config('attendance.timezone'))->toDateString()) {
                    throw ValidationException::withMessages(['effective_from' => 'Revisions keep their assignment scope and take effect on or after today and the original start date. Create a separate schedule for a different assignment.']);
                }
                if (WorkSchedule::where('previous_id', $previous->id)->exists()) {
                    throw ValidationException::withMessages(['effective_from' => 'Revise the latest version for this assignment.']);
                }
            }

            if ($previous) {
                $this->endOn($previous, $data['effective_from']);
            }
            foreach ($targets as $scope) {
                $overlap = WorkSchedule::where($scope)->where('active', true)
                    ->whereDate('effective_from', '<=', $data['effective_until'] ?? '9999-12-31')
                    ->where(fn (Builder $query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $data['effective_from']))
                    ->exists();
                if ($overlap) {
                    throw ValidationException::withMessages(['effective_from' => 'A selected assignment already has an active schedule in that date range. Revise the existing schedule or use a non-overlapping period.']);
                }
                $schedule = WorkSchedule::create([
                    ...Arr::except($data, ['scope', 'employee_id', 'department_id', 'branch_id', 'employee_ids', 'department_ids', 'branch_ids']), ...$scope,
                    'working_days' => array_map('intval', $data['working_days']), 'active' => true,
                    'previous_id' => $previous?->id, 'created_by' => $request->user()->id,
                ]);
                $this->audit->record('attendance.schedule_saved', $schedule, ['previous_id' => $previous?->id]);
            }
        });
    }

    public function deactivate(Request $request, WorkSchedule $schedule): RedirectResponse
    {
        $request->validate(['confirmation' => ['accepted']]);
        $this->administration->run(function () use ($schedule): void {
            $schedule->refresh();
            $this->endOn($schedule, now(config('attendance.timezone'))->toDateString());
            $this->audit->record('attendance.schedule_deactivated', $schedule);
        });

        return to_route('hr.schedules.index')->with('status', 'Schedule deactivated from today.');
    }

    private function endOn(WorkSchedule $schedule, string $firstInactiveDate): void
    {
        if ($firstInactiveDate <= $schedule->effective_from->toDateString()) {
            $schedule->update(['active' => false]);

            return;
        }

        $end = CarbonImmutable::parse($firstInactiveDate)->subDay();
        if (! $schedule->effective_until || $schedule->effective_until->gt($end)) {
            $schedule->update(['effective_until' => $end->toDateString()]);
        }
    }

    public function storeException(Request $request, AttendancePolicyResolver $resolver): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'], 'work_date' => ['required', 'date_format:Y-m-d'],
            'kind' => ['required', Rule::in(array_keys(WorkScheduleException::KINDS))], 'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'work_schedule_id' => ['exclude_unless:kind,shift', 'required', 'integer', 'exists:work_schedules,id'],
            'adjusted_end' => ['exclude_unless:kind,early_departure', 'required', 'date_format:H:i'],
        ]);

        $this->administration->run(function () use ($request, $resolver, $data): void {
            $date = $data['work_date'];
            $employee = Employee::findOrFail($data['employee_id']);
            if ($data['kind'] === 'shift' && ! WorkSchedule::effectiveOn($date)->whereKey($data['work_schedule_id'])->exists()) {
                throw ValidationException::withMessages(['work_schedule_id' => 'Select a schedule version effective on the approved shift date.']);
            }

            if ($data['kind'] === 'early_departure') {
                $branch = $resolver->branchFor($employee, $date);
                $schedule = $resolver->schedule($employee, $date, $branch?->id);
                $offset = $schedule ? WorkDuration::offset($data['adjusted_end'], $schedule->starts_at) : 0;
                if (! $schedule || $offset === 0 || $offset >= WorkDuration::offset($schedule->ends_at, $schedule->starts_at)) {
                    throw ValidationException::withMessages(['adjusted_end' => 'Enter a departure time strictly within the assigned shift.']);
                }
            }

            if (WorkScheduleException::where('employee_id', $employee->id)->whereDate('work_date', $date)->where('active', true)->exists()) {
                throw ValidationException::withMessages(['work_date' => 'Deactivate the existing exception for this employee and date before creating another.']);
            }
            $exception = WorkScheduleException::create([...$data, 'created_by' => $request->user()->id, 'active' => true]);
            $this->audit->record('attendance.schedule_exception_approved', $exception, ['reason' => $data['reason']]);
        });

        return to_route('hr.schedules.index')->with('status', 'Attendance exception approved.');
    }

    public function cancelException(Request $request, WorkScheduleException $exception): RedirectResponse
    {
        $request->validate(['confirmation' => ['accepted']]);
        $this->administration->run(function () use ($exception): void {
            $exception->update(['active' => false]);
            $this->audit->record('attendance.schedule_exception_cancelled', $exception);
        });

        return to_route('hr.schedules.index')->with('status', 'Attendance exception deactivated.');
    }
}

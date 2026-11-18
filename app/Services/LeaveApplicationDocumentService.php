<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveApprovalDocument;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\SupervisorSignature;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class LeaveApplicationDocumentService
{
    public const TEMPLATE = 'HRD No. 23/Rev.2';

    public const WATERMARK_ASSET = 'LIMCOMA.png';

    public function __construct(private LeavePdfRenderer $renderer, private SupervisorSignatureService $signatures) {}

    public function snapshot(Employee $employee, LeaveType $type, ?string $other): array
    {
        $category = $this->categoryFor($type);
        if ($category === 'other' && $this->isGenericOther($type) && blank($other)) {
            throw ValidationException::withMessages(['other_leave_type' => 'Specify the type of leave.']);
        }

        return array_merge($this->identity($employee), [
            'category' => $category,
            'other' => $category === 'other' ? ($this->isGenericOther($type) ? $other : $type->name) : null,
        ]);
    }

    public function fields(LeaveRequest $leave): array
    {
        return array_merge($leave->form_snapshot ?? $this->legacySnapshot($leave), [
            'type' => $leave->type_snapshot['name'],
            'filed' => $leave->submitted_at->copy()->timezone(config('attendance.timezone'))->format('M j, Y'),
            'dates' => $leave->start_date->format('M j, Y').' – '.$leave->end_date->format('M j, Y'),
            'days' => rtrim(rtrim($leave->requested_duration, '0'), '.').($leave->half_day ? ' ('.strtoupper($leave->half_day).')' : ''),
            'reason' => $leave->reason,
        ]);
    }

    public function legacySnapshot(LeaveRequest $leave): array
    {
        $typeName = $leave->type_snapshot['name'];

        return array_merge($this->identity($leave->employee), [
            'category' => $this->category($typeName),
            'other' => $typeName,
        ]);
    }

    public function categoryFor(LeaveType $type): string
    {
        return $this->category($type->name, $type->code);
    }

    public function isGenericOther(LeaveType $type): bool
    {
        return in_array(Str::slug($type->name), ['other', 'others', 'other-leave'], true);
    }

    public function generate(LeaveRequest $leave, User $approver, SupervisorSignature $signature, Carbon $approvedAt, string $path): LeaveApprovalDocument
    {
        $reference = 'LMPC-LA-'.$leave->id.'-01';
        try {
            $html = view('leave.official-form', [
                'fields' => $this->fields($leave),
                'templateReference' => self::TEMPLATE,
                'watermarkSource' => 'data:image/png;base64,'.base64_encode(File::get(public_path(self::WATERMARK_ASSET))),
                'approval' => [
                    'name' => $approver->name,
                    'signedAt' => $approvedAt->copy()->timezone(config('attendance.timezone'))->format('M j, Y H:i:s T'),
                    'signature' => base64_encode($this->signatures->bytes($signature)),
                    'reference' => $reference,
                    'chargedDays' => $leave->type_snapshot['affects_balance'] ? $leave->charged_duration : null,
                ],
            ])->render();
            $pdf = $this->renderer->render($html);
            Storage::disk('leave')->put($path, Crypt::encryptString($pdf));
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['decision' => 'The official PDF could not be saved. The application is still pending. Try again or contact support.']);
        }

        return LeaveApprovalDocument::create([
            'leave_request_id' => $leave->id,
            'approved_by_user_id' => $approver->id,
            'supervisor_signature_id' => $signature->id,
            'document_reference' => $reference,
            'storage_path' => $path,
            'sha256_hash' => hash('sha256', $pdf),
            'version' => 1,
            'template_reference' => self::TEMPLATE,
            'approver_name' => $approver->name,
            'generated_at' => $approvedAt,
        ]);
    }

    public function bytes(LeaveApprovalDocument $document): string
    {
        $disk = Storage::disk('leave');
        abort_unless($disk->exists($document->storage_path), 404);
        $pdf = Crypt::decryptString($disk->get($document->storage_path));
        abort_unless(hash_equals($document->sha256_hash, hash('sha256', $pdf)), 503, 'The stored leave document could not be verified.');

        return $pdf;
    }

    private function identity(Employee $employee): array
    {
        return [
            'name' => $employee->full_name,
            'department' => $employee->department?->department_name,
            'branch' => $employee->currentAssignment?->branch?->branch_name,
        ];
    }

    private function category(string $name, ?string $code = null): string
    {
        foreach ([$name, $code ?? ''] as $identifier) {
            $category = match (Str::slug($identifier)) {
                'vacation-leave', 'vacation', 'vl' => 'vacation',
                'sick-leave', 'sick', 'sl' => 'sick',
                default => null,
            };
            if ($category) {
                return $category;
            }
        }

        return 'other';
    }
}

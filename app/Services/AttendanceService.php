<?php

namespace App\Services;

use App\Models\AttendanceAssistanceRequest;
use App\Models\AttendanceCaptureSession;
use App\Models\AttendanceEvent;
use App\Models\AttendancePhotoEvidence;
use App\Models\AttendanceRecord;
use App\Models\AttendanceReview;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(private GeofencingService $geofencing, private AttendancePhotoStorage $photos, private AuditService $audit) {}

    public function employee(User $actor): Employee
    {
        abort_unless($actor->canAuthenticate(), 403);
        abort_if($actor->isSupervisor(), 403);
        if (! $actor->employee) {
            throw ValidationException::withMessages(['employee' => 'No employee profile is linked. Ask HR to configure attendance eligibility.']);
        }

        return $actor->employee;
    }

    private function locked(User $actor, Closure $operation): mixed
    {
        return DB::transaction(function () use ($actor, $operation) {
            Role::whereKey(Role::HR)->lockForUpdate()->firstOrFail();
            $fresh = User::findOrFail($actor->id);
            $employee = Employee::whereKey($this->employee($fresh)->id)->lockForUpdate()->firstOrFail();

            return $operation($employee, $fresh);
        });
    }

    public function assignment(Employee $employee): mixed
    {
        $assignment = $employee->assignments()->with('branch')->where('is_primary', true)->whereNull('effective_until')->where('effective_from', '<=', now())->first();
        if (! $assignment || $assignment->branch->status !== 'active') {
            throw ValidationException::withMessages(['assignment' => 'A current primary assignment to an active branch is required. Contact HR.']);
        }

        return $assignment;
    }

    public function snapshot(mixed $assignment): array
    {
        $branch = $assignment->branch;

        return ['branch_id' => $branch->id, 'branch_code' => $branch->branch_code, 'branch_name' => $branch->branch_name, 'latitude' => $branch->latitude, 'longitude' => $branch->longitude, 'radius' => $branch->geofence_radius_meters, 'accuracy_limit' => (string) config('attendance.max_accuracy_meters'), 'max_age_seconds' => (int) config('attendance.location_max_age_seconds'), 'policy' => config('attendance.policy_version')];
    }

    public function open(Employee $employee): ?AttendanceRecord
    {
        return AttendanceRecord::where('employee_id', $employee->id)->whereNull('effective_time_out_at')->whereIn('session_status', ['open', 'pending'])->first();
    }

    private function ensureAction(Employee $employee, string $action): ?AttendanceRecord
    {
        $open = $this->open($employee);
        if ($open?->review_status === 'pending') {
            throw ValidationException::withMessages(['attendance' => 'Your previous submission is awaiting HR review.']);
        }
        if ($action === 'time_in' && $open) {
            throw ValidationException::withMessages(['attendance' => 'You already have an open attendance session. Record Time Out first.']);
        }
        if ($action === 'time_out' && (! $open || ! $open->time_in_at)) {
            throw ValidationException::withMessages(['attendance' => 'No eligible Time In exists. Ask HR for assistance; no completed attendance was created.']);
        }

        return $open;
    }

    private function binding(User $actor): string
    {
        return hash('sha256', request()->session()->getId().'|'.$actor->session_version.'|'.$actor->password);
    }

    public function start(User $actor, string $action): array
    {
        return $this->locked($actor, function ($employee, $fresh) use ($action) {
            $record = $this->ensureAction($employee, $action);
            $assignment = $this->assignment($employee);
            if ($record && $record->branch_id !== $assignment->branch_id) {
                throw ValidationException::withMessages(['assignment' => 'Your branch changed during the open session. Contact HR for assistance.']);
            }
            $token = bin2hex(random_bytes(32));
            $capture = AttendanceCaptureSession::create(['id' => (string) Str::uuid(), 'employee_id' => $employee->id, 'branch_id' => $assignment->branch_id, 'assignment_id' => $assignment->id, 'attendance_record_id' => $record?->id, 'action' => $action, 'token_hash' => hash('sha256', $token), 'session_hash' => $this->binding($fresh), 'attendance_version' => $employee->attendance_version, 'branch_snapshot' => $this->snapshot($assignment), 'expires_at' => now()->addSeconds(config('attendance.capture_seconds'))]);

            return ['id' => $capture->id, 'token' => $token, 'expires_at' => $capture->expires_at->toIso8601String(), 'branch' => $capture->branch_snapshot, 'action' => $action, 'server_time' => now()->toIso8601String()];
        });
    }

    public function authorizeCapture(User $actor, AttendanceCaptureSession $capture, ?string $token = null): void
    {
        abort_unless($this->employee($actor)->id === $capture->employee_id && hash_equals($capture->session_hash, $this->binding($actor)), 403);
        if ($token !== null) {
            abort_unless(hash_equals($capture->token_hash, hash('sha256', $token)), 403);
        }
    }

    private function freshCapture(AttendanceCaptureSession $capture, Employee $employee): void
    {
        abort_if($capture->expires_at->isPast(), 409, 'This capture session expired. Start again and take a fresh photo.');
        abort_if($capture->attendance_version !== (int) $employee->attendance_version, 409, 'Attendance changed in another request. Check your history before starting again.');
        $assignment = $this->assignment($employee);
        abort_if($capture->assignment_id !== $assignment->id || $capture->branch_snapshot !== $this->snapshot($assignment), 409, 'The assignment or geofence policy changed. Start a new capture.');
    }

    public function preview(User $actor, AttendanceCaptureSession $capture, array $input): array
    {
        $this->authorizeCapture($actor, $capture, $input['token']);
        $this->freshCapture($capture, $this->employee($actor));
        abort_if($capture->consumed_at, 409, 'This capture session was already submitted.');

        return $this->geofencing->evaluate($capture->branch_snapshot, $input, (int) now()->valueOf());
    }

    public function submit(User $actor, AttendanceCaptureSession $capture, array $data, UploadedFile $file, Carbon $receivedAt): array
    {
        $this->authorizeCapture($actor, $capture, $data['token']);
        $photo = $this->photos->prepare($file);
        $fingerprint = hash('sha256', json_encode([$photo['file_hash'], array_intersect_key($data, array_flip(['latitude', 'longitude', 'accuracy', 'retrieved_at', 'request_review', 'exception_reason']))], JSON_PRESERVE_ZERO_FRACTION));
        $path = $this->photos->store($photo);
        $kept = false;
        $linked = false;
        try {
            $result = $this->locked($actor, function ($employee, $fresh) use ($capture, $data, $photo, $fingerprint, $path, &$linked, $receivedAt) {
                $capture = AttendanceCaptureSession::whereKey($capture->id)->lockForUpdate()->firstOrFail();
                $this->authorizeCapture($fresh, $capture, $data['token']);
                if ($capture->consumed_at) {
                    abort_unless(hash_equals($capture->request_hash, $fingerprint), 409, 'This capture was already used with different evidence.');

                    return $this->result($capture->event, $employee);
                }
                $this->freshCapture($capture, $employee);
                $open = $this->ensureAction($employee, $capture->action);
                if ($capture->action === 'time_out' && $receivedAt->lt($open->time_in_at)) {
                    throw ValidationException::withMessages(['attendance' => 'The server clock precedes Time In. Contact HR; no Time Out was recorded.']);
                }
                abort_if($open?->id !== $capture->attendance_record_id, 409, 'The attendance session changed. Refresh your attendance page.');
                $geo = $this->geofencing->evaluate($capture->branch_snapshot, $data, (int) now()->valueOf());
                $reused = AttendancePhotoEvidence::where('file_hash', $photo['file_hash'])->whereHas('event', fn ($query) => $query->where('employee_id', $employee->id))->exists();
                $needsReview = ! $geo['validated'] || $reused;
                if ($needsReview && (! config('attendance.allow_review_requests') || empty($data['request_review']) || mb_strlen($data['exception_reason'] ?? '') < 10)) {
                    throw ValidationException::withMessages(['location' => $reused ? 'This image matches previous evidence. Retake the photo, or explicitly request HR review.' : 'Location did not pass ('.str_replace('_', ' ', $geo['status']).'). Retry, or explicitly request HR review.']);
                }
                $verification = $needsReview ? 'pending_review' : 'location_validated';
                $review = $needsReview ? 'pending' : 'not_required';
                $record = $open ?? AttendanceRecord::create(['employee_id' => $employee->id, 'branch_id' => $capture->branch_id, 'work_date' => $receivedAt->copy()->timezone(config('attendance.timezone'))->toDateString(), 'session_status' => 'pending', 'verification_status' => $verification, 'review_status' => $review]);
                $location = isset($data['latitude']) ? collect($data)->only(['latitude', 'longitude', 'accuracy', 'retrieved_at'])->all() : null;
                $event = AttendanceEvent::create(['attendance_record_id' => $record->id, 'employee_id' => $employee->id, 'branch_id' => $capture->branch_id, 'capture_session_id' => $capture->id, 'event_type' => $capture->action, 'occurred_at' => $receivedAt, 'location' => $location, 'accuracy_meters' => $data['accuracy'] ?? null, 'distance_meters' => $geo['distance'], 'geofence_status' => $geo['status'], 'verification_status' => $verification, 'review_status' => $review, 'exception_reason' => $needsReview ? ($reused ? 'Repeated image signal. ' : '').$data['exception_reason'] : null, 'branch_snapshot' => $capture->branch_snapshot]);
                $event->photo()->create([...collect($photo)->except('bytes')->all(), 'storage_disk' => 'attendance', 'storage_path' => $path, 'mime_type' => 'image/jpeg']);
                $capture->update(['consumed_at' => now(), 'request_hash' => $fingerprint]);
                $employee->increment('attendance_version');
                $this->refreshRecord($record);
                $this->audit->record($needsReview ? 'attendance.flagged' : 'attendance.'.$capture->action.'_recorded', $event, ['record_id' => $record->id, 'geofence_status' => $geo['status']]);
                $this->audit->record('attendance.submitted', $event);
                $linked = true;

                return $this->result($event, $employee);
            });
            $kept = $linked;

            return $result;
        } finally {
            if (! $kept) {
                $this->photos->discard($path);
            }
        }
    }

    private function refreshRecord(AttendanceRecord $record): void
    {
        $events = $record->events()->orderBy('id')->get();
        $accepted = $events->whereIn('verification_status', ['location_validated', 'exception_approved']);
        $in = $accepted->firstWhere('event_type', 'time_in');
        $out = $accepted->firstWhere('event_type', 'time_out');
        $pending = $events->where('review_status', 'pending')->isNotEmpty();
        $status = $in ? ($out ? 'completed' : 'open') : ($pending ? 'pending' : 'cancelled');
        $verification = $pending ? 'pending_review' : ($accepted->contains('verification_status', 'exception_approved') ? 'exception_approved' : ($in ? 'location_validated' : 'rejected'));
        $record->update(['time_in_at' => $in?->occurred_at, 'time_out_at' => $out?->occurred_at, 'session_status' => $status, 'verification_status' => $verification, 'review_status' => $pending ? 'pending' : ($events->contains('review_status', 'approved') ? 'approved' : ($events->contains('review_status', 'rejected') ? 'rejected' : 'not_required'))]);
    }

    public function review(User $actor, AttendanceEvent $event, string $decision, string $remarks): void
    {
        app(Administration::class)->run(function () use ($actor, $event, $decision, $remarks) {
            $event = AttendanceEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $employee = Employee::findOrFail($event->employee_id);
            abort_if($employee->user_id === $actor->id, 403, 'Another HR colleague must review your own attendance.');
            abort_unless($event->review_status === 'pending', 409, 'This event is not awaiting a decision.');
            if ($decision === 'approved' && (! $event->photo || $event->photo->purged_at || ! Storage::disk('attendance')->exists($event->photo->storage_path))) {
                throw ValidationException::withMessages(['review' => 'Photo evidence is unavailable; this event cannot be approved.']);
            }
            AttendanceReview::create(['attendance_event_id' => $event->id, 'reviewer_user_id' => $actor->id, 'action' => $decision, 'remarks' => $remarks, 'reviewed_at' => now()]);
            $event->update(['review_status' => $decision, 'verification_status' => $decision === 'approved' ? 'exception_approved' : 'rejected']);
            $this->refreshRecord($event->record);
            $employee->increment('attendance_version');
            $this->audit->record('attendance.review_'.$decision, $event, ['review_id' => $event->review->id]);
        });
    }

    public function availability(Employee $employee): array
    {
        $eligible = true;
        $assignment = null;
        $eligibilityError = null;
        try {
            $assignment = $this->assignment($employee);
        } catch (ValidationException $exception) {
            $eligible = false;
            $eligibilityError = collect($exception->errors())->flatten()->first();
        }
        $open = $this->open($employee);

        return ['canTimeIn' => $eligible && ! $open, 'canTimeOut' => $eligible && $open?->time_in_at !== null && $open?->review_status !== 'pending', 'label' => $open ? ($open->review_status === 'pending' ? 'Pending HR review' : 'Clocked in') : 'Not clocked in', 'pendingReview' => $open?->review_status === 'pending', 'eligibilityError' => $eligibilityError, 'employeeName' => $employee->full_name, 'employeeSummary' => $employee->employee_number.' · '.($assignment?->branch->branch_name ?? 'No eligible branch')];
    }

    public function status(User $actor, AttendanceCaptureSession $capture): array
    {
        $this->authorizeCapture($actor, $capture);

        return $this->locked($actor, function (Employee $employee, User $fresh) use ($capture): array {
            $capture = AttendanceCaptureSession::whereKey($capture->id)->lockForUpdate()->firstOrFail();
            $this->authorizeCapture($fresh, $capture);
            if ($capture->consumed_at) {
                return ['status' => 'succeeded', 'submitted' => true, 'result' => $this->result($capture->event, $employee)];
            }

            return ['status' => $capture->expires_at->isPast() ? 'not_recorded' : 'unknown', 'submitted' => false, 'expired' => $capture->expires_at->isPast(), 'attendance' => $this->availability($employee)];
        });
    }

    public function result(AttendanceEvent $event, ?Employee $employee = null): array
    {
        $message = match ($event->verification_status) {
            'pending_review' => 'Submission saved as Pending HR Review. It is not location-validated attendance.',
            'exception_approved' => 'HR approved this exception. The original location result and server timestamp are preserved.',
            'rejected' => 'HR rejected this submission. It is not accepted attendance.',
            default => 'Attendance saved. Reported location passed the configured checks; photo origin and physical presence are not independently proven.',
        };

        return ['event_id' => $event->id, 'record_id' => $event->attendance_record_id, 'action' => $event->event_type, 'occurred_at' => $event->occurred_at->toIso8601String(), 'verification_status' => $event->verification_status, 'review_status' => $event->review_status, 'attendance' => $this->availability($employee ?? Employee::findOrFail($event->employee_id)), 'geofence_status' => $event->geofence_status, 'branch' => $event->branch_snapshot['branch_name'], 'message' => $message, 'record_url' => route('attendance.show', $event->attendance_record_id)];
    }

    public function requestAssistance(User $actor, array $data): void
    {
        $this->locked($actor, function ($employee) use ($data) {
            abort_if(AttendanceAssistanceRequest::where('employee_id', $employee->id)->where('status', 'pending')->count() >= 3, 409, 'You already have pending assistance requests. Contact HR.');
            $request = AttendanceAssistanceRequest::create(['employee_id' => $employee->id, ...$data]);
            $this->audit->record('attendance.assistance_requested', $request);
        });
    }
}

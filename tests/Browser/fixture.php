<?php

use App\Models\AccountInvitation;
use App\Models\AttendanceAssistanceRequest;
use App\Models\AttendanceCaptureSession;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceEvent;
use App\Models\AttendancePhotoEvidence;
use App\Models\AttendanceRecord;
use App\Models\AttendanceReview;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeBranchAssignment;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveRequest;
use App\Models\LeaveReview;
use App\Models\LeaveSupportingDocument;
use App\Models\LeaveType;
use App\Models\LocalMailMessage;
use App\Models\Role;
use App\Models\SupervisorAssignment;
use App\Models\User;
use App\Services\LeaveService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local')) {
    throw new RuntimeException('Browser fixtures are restricted to local development.');
}

if (($argv[1] ?? '') === 'create') {
    $marker = bin2hex(random_bytes(10));
    $password = bin2hex(random_bytes(20)).'aA!';
    $user = User::create(['name' => 'Synthetic Browser HR', 'email' => 'browser-qa-'.$marker.'@example.test', 'password' => $password]);
    $user->forceFill(['role_id' => Role::HR, 'account_status' => 'active', 'email_verified_at' => now()])->save();
    echo json_encode(['id' => $user->id, 'email' => $user->email, 'password' => $password, 'prefix' => 'QA-'.substr($marker, 0, 10)]);
} elseif (($argv[1] ?? '') === 'attendance') {
    $actor = User::findOrFail((int) ($argv[2] ?? 0));
    if (! preg_match('/^browser-qa-([a-f0-9]{20})@example\.test$/', $actor->email, $matches)) {
        throw new RuntimeException('Fixture account required.');
    }
    $prefix = 'QA-'.substr($matches[1], 0, 10);
    $password = bin2hex(random_bytes(20)).'aA!';
    $user = User::create(['name' => 'Synthetic Attendance Employee', 'email' => strtolower($prefix).'-employee@example.test', 'password' => $password]);
    $user->forceFill(['account_status' => 'active', 'email_verified_at' => now()])->save();
    $branch = Branch::factory()->create(['branch_code' => $prefix.'-BRANCH', 'branch_name' => $prefix.' Synthetic Office', 'latitude' => 14.5995, 'longitude' => 120.9842, 'geofence_radius_meters' => 150]);
    $department = Department::factory()->create(['department_code' => $prefix.'-DEPT']);
    $employee = Employee::factory()->create(['user_id' => $user->id, 'employee_number' => $prefix.'-EMP', 'department_id' => $department->id]);
    $employee->assignments()->create(['branch_id' => $branch->id, 'is_primary' => true, 'effective_from' => now()->subDay()]);
    echo json_encode(['id' => $user->id, 'email' => $user->email, 'password' => $password, 'branch' => $branch->branch_name]);
} elseif (($argv[1] ?? '') === 'phase5') {
    $actor = User::findOrFail((int) ($argv[2] ?? 0));
    if (! preg_match('/^browser-qa-([a-f0-9]{20})@example\.test$/', $actor->email, $matches)) {
        throw new RuntimeException('Fixture account required.');
    }
    $prefix = 'QA-'.substr($matches[1], 0, 10);
    $employee = Employee::where('employee_number', $prefix.'-EMP')->sole();
    $password = bin2hex(random_bytes(20)).'aA!';
    $supervisor = User::create(['name' => 'Synthetic Leave Supervisor', 'email' => strtolower($prefix).'-supervisor@example.test', 'password' => $password]);
    $supervisor->forceFill(['supervisor_department_id' => $employee->department_id, 'role_id' => Role::SUPERVISOR, 'account_status' => 'active', 'email_verified_at' => now()])->save();
    $type = LeaveType::factory()->create(['code' => $prefix.'-LEAVE', 'name' => $prefix.' Synthetic Leave', 'affects_balance' => true, 'requires_document' => true]);
    Auth::login($actor);
    app(LeaveService::class)->adjustBalance($actor, ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'policy_period' => now()->format('Y'), 'amount' => 5, 'reason' => 'Synthetic browser test credit; not company policy.']);
    echo json_encode(['id' => $supervisor->id, 'email' => $supervisor->email, 'password' => $password, 'employee_id' => $employee->id, 'type_id' => $type->id, 'type' => $type->name, 'from' => now(config('attendance.timezone'))->addDays(4)->toDateString(), 'until' => now(config('attendance.timezone'))->addDays(5)->toDateString(), 'correction_date' => now(config('attendance.timezone'))->subDay()->toDateString()]);
} elseif (($argv[1] ?? '') === 'cleanup') {
    $actor = User::findOrFail((int) ($argv[2] ?? 0));
    if (! preg_match('/^browser-qa-([a-f0-9]{20})@example\.test$/', $actor->email, $matches)) {
        throw new RuntimeException('Refusing to remove a non-fixture account.');
    }
    $prefix = 'QA-'.substr($matches[1], 0, 10);
    DB::transaction(function () use ($actor, $prefix) {
        $users = User::whereKey($actor->id)->orWhereIn('email', [strtolower($prefix).'-employee@example.test', strtolower($prefix).'-hr@example.test', strtolower($prefix).'-supervisor@example.test', strtolower($prefix).'-realtime@example.test'])->get();
        $userIds = $users->pluck('id');
        $employees = Employee::whereIn('user_id', $userIds)->get();
        $leaveIds = LeaveRequest::whereIn('employee_id', $employees->pluck('id'))->pluck('id');
        $correctionIds = AttendanceCorrectionRequest::whereIn('employee_id', $employees->pluck('id'))->pluck('id');
        DB::table('notifications')->where('data->category', 'leave')->whereIn('data->record_id', $leaveIds)->delete();
        DB::table('notifications')->where('data->category', 'correction')->whereIn('data->record_id', $correctionIds)->delete();
        DB::table('notifications')->where('notifiable_type', User::class)->whereIn('notifiable_id', $userIds)->delete();
        foreach (LeaveSupportingDocument::whereIn('leave_request_id', $leaveIds)->get() as $document) {
            Storage::disk('leave')->delete($document->storage_path);
            $document->delete();
        }
        LeaveReview::whereIn('leave_request_id', $leaveIds)->delete();
        $balanceIds = EmployeeLeaveBalance::whereIn('employee_id', $employees->pluck('id'))->pluck('id');
        LeaveBalanceTransaction::whereIn('employee_leave_balance_id', $balanceIds)->delete();
        EmployeeLeaveBalance::whereIn('id', $balanceIds)->delete();
        LeaveRequest::whereIn('id', $leaveIds)->delete();
        AttendanceCorrectionRequest::whereIn('id', $correctionIds)->delete();
        SupervisorAssignment::whereIn('employee_id', $employees->pluck('id'))->orWhereIn('supervisor_user_id', $userIds)->delete();
        LeaveType::where('code', $prefix.'-LEAVE')->delete();
        $eventIds = AttendanceEvent::whereIn('employee_id', $employees->pluck('id'))->pluck('id');
        $recordIds = AttendanceRecord::whereIn('employee_id', $employees->pluck('id'))->pluck('id');
        $assistanceIds = AttendanceAssistanceRequest::whereIn('employee_id', $employees->pluck('id'))->pluck('id');
        foreach (['AttendanceEvent' => $eventIds, 'AttendanceRecord' => $recordIds, 'AttendanceAssistanceRequest' => $assistanceIds] as $type => $ids) {
            AuditLog::where('subject_type', $type)->whereIn('subject_id', $ids)->delete();
        }
        foreach (AttendancePhotoEvidence::whereIn('attendance_event_id', $eventIds)->get() as $photo) {
            if ($photo->storage_path) {
                Storage::disk('attendance')->delete($photo->storage_path);
            } $photo->delete();
        }
        AttendanceReview::whereIn('attendance_event_id', $eventIds)->delete();
        AttendanceEvent::whereIn('id', $eventIds)->delete();
        AttendanceCaptureSession::whereIn('employee_id', $employees->pluck('id'))->delete();
        AttendanceRecord::whereIn('id', $recordIds)->delete();
        AttendanceAssistanceRequest::whereIn('id', $assistanceIds)->delete();
        $invitationIds = AccountInvitation::whereIn('user_id', $userIds)->pluck('id');
        AuditLog::whereIn('actor_user_id', $userIds)->orWhere(fn ($q) => $q->where('subject_type', 'User')->whereIn('subject_id', $userIds))->orWhere(fn ($q) => $q->where('subject_type', 'Employee')->whereIn('subject_id', $employees->pluck('id')))->orWhere(fn ($q) => $q->where('subject_type', 'AccountInvitation')->whereIn('subject_id', $invitationIds))->delete();
        LocalMailMessage::whereIn('recipient_hash', $users->map(fn ($u) => hash('sha256', $u->email)))->delete();
        AccountInvitation::whereIn('user_id', $userIds)->delete();
        EmployeeBranchAssignment::whereIn('employee_id', $employees->pluck('id'))->delete();
        Employee::whereIn('id', $employees->pluck('id'))->delete();
        DB::table('sessions')->whereIn('user_id', $userIds)->delete();
        DB::table('password_reset_tokens')->whereIn('email', $users->pluck('email'))->delete();
        foreach ($users as $user) {
            if ($user->profile_photo_path && preg_match('~^profile-photos/[a-f0-9-]{36}\.jpg$~D', $user->profile_photo_path)) {
                Storage::disk('local')->delete($user->profile_photo_path);
            }
        }
        User::whereIn('id', $userIds)->delete();
        // Restrictive FKs reject cleanup if a non-fixture employee references these records.
        Branch::whereIn('branch_code', [$prefix.'-BRANCH', $prefix.'-MAP'])->delete();
        Department::where('department_code', $prefix.'-DEPT')->delete();
    });
}

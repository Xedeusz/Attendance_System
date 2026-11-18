<?php

namespace App\Services;

use App\Jobs\BroadcastWorkspaceChange;
use App\Models\Employee;
use App\Models\LeaveSupportingDocument;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\SupervisorAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class WorkspaceSync
{
    public static function channel(User $user): string
    {
        return 'workspace.'.$user->id.'.'.$user->session_version.'.'.$user->role_id;
    }

    /** @return list<string> */
    public function topics(User $user): array
    {
        return $user->isSupervisor() ? ['leave', 'notifications', 'account'] : ['employees', 'branches', 'departments', 'attendance', 'leave', 'corrections', 'notifications', 'account'];
    }

    /** @param array<string, mixed> $metadata */
    public function record(string $action, Model $subject, array $metadata = []): void
    {
        if (in_array($action, ['attendance.validation_failed', 'leave.review_opened', 'leave.signing_confirmed', 'leave.official_pdf_generated', 'leave.approved_pdf_accessed'], true)) {
            return;
        }
        if ($subject instanceof LeaveSupportingDocument) {
            $subject = $subject->leave;
        }
        $topic = match (explode('.', $action)[0]) {
            'branch' => 'branches',
            'department' => 'departments',
            'employee', 'profile', 'hr', 'account', 'invitation' => 'employees',
            'supervisor', 'leave' => 'leave',
            'attendance' => str_contains($action, 'correction') ? 'corrections' : 'attendance',
            default => null,
        };
        if (! $topic) {
            return;
        }
        $topics = $topic === 'corrections' ? ['corrections', 'attendance'] : [$topic];
        if (str_starts_with($action, 'supervisor.')) {
            $topics[] = 'employees';
        }
        $recipients = User::where('role_id', Role::HR)->where('account_status', 'active')->pluck('id')->all();
        $employeeId = $subject instanceof Employee ? $subject->id : $subject->getAttribute('employee_id');
        if ($employeeId) {
            $recipients[] = Employee::find($employeeId)?->user_id;
        }
        if ($subject instanceof User) {
            $recipients[] = $subject->id;
        } elseif ($subject->getAttribute('user_id')) {
            $recipients[] = $subject->getAttribute('user_id');
        }
        if ($topic === 'leave') {
            $recipients[] = $subject->getAttribute('assigned_supervisor_user_id');
            $recipients[] = $subject->getAttribute('supervisor_user_id');
            $recipients[] = $metadata['previous_supervisor_id'] ?? null;
            if ($subject instanceof LeaveType || $subject instanceof User) {
                $recipients = array_merge($recipients, User::where('account_status', 'active')->pluck('id')->all());
            }
        }
        if (in_array($topic, ['branches', 'departments'], true)) {
            $employees = Employee::query();
            if ($topic === 'branches') {
                $employees->whereHas('currentAssignment', fn ($query) => $query->where('branch_id', $subject->getKey()));
            } else {
                $employees->where('department_id', $subject->getKey());
            }
            $recipients = array_merge($recipients, $employees->pluck('user_id')->all());
        }
        foreach (User::whereIn('id', array_unique(array_filter($recipients)))->where('account_status', 'active')->get() as $recipient) {
            $this->touch($recipient, array_values(array_intersect($topics, $this->topics($recipient))));
        }
        if ($topic === 'departments') {
            foreach (User::where('role_id', Role::SUPERVISOR)->where('supervisor_department_id', $subject->getKey())->where('account_status', 'active')->get() as $supervisor) {
                $this->touch($supervisor, ['leave']);
            }
        }
        if (in_array($topic, ['employees', 'branches', 'departments'], true)) {
            $affected = Employee::whereIn('user_id', array_filter($recipients))->pluck('id');
            $supervisors = SupervisorAssignment::query()->whereIn('employee_id', $affected)->pluck('supervisor_user_id');
            foreach (User::whereIn('id', $supervisors)->where('role_id', Role::SUPERVISOR)->where('account_status', 'active')->get() as $supervisor) {
                $this->touch($supervisor, ['leave']);
            }
        }
    }

    /** @param list<string> $topics */
    public function touch(User $user, array $topics): void
    {
        $topics = array_values(array_intersect($topics, $this->topics($user)));
        if ($topics === []) {
            return;
        }
        $revisions = [];
        foreach ($topics as $topic) {
            $revision = (string) Str::uuid();
            DB::table('workspace_revisions')->upsert([['user_id' => $user->id, 'topic' => $topic, 'revision' => $revision]], ['user_id', 'topic'], ['revision']);
            $revisions[$topic] = $revision;
        }
        $userId = $user->id;
        $sessionVersion = $user->session_version;
        $roleId = $user->role_id;
        DB::afterCommit(function () use ($userId, $sessionVersion, $roleId, $revisions): void {
            try {
                BroadcastWorkspaceChange::dispatch($userId, $sessionVersion, $roleId, $revisions);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}

<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\SupervisorAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupervisorService
{
    public function __construct(private WorkflowTransaction $transactions, private AccountService $accounts, private InvitationService $invitations, private AuditService $audit, private LeaveService $leave) {}

    public function save(User $actor, array $data, ?User $target = null): array
    {
        return $this->transactions->run($actor, function (User $fresh) use ($data, $target) {
            abort_unless($fresh->isHr(), 403);
            $new = $target === null;
            if ($target) {
                $user = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
                abort_unless($user->isSupervisor(), 404);
                EditVersion::check($user, $data['_edit_version'] ?? null);
            } elseif (! empty($data['employee_id'])) {
                $employee = Employee::with('user')->findOrFail($data['employee_id']);
                $user = $employee->user;
                if (! $user->isEmployee()) {
                    throw ValidationException::withMessages(['employee_id' => 'Choose an Employee account that has not already been promoted.']);
                }
                if (mb_strtolower($user->email) !== $data['email']) {
                    throw ValidationException::withMessages(['email' => 'Use the linked employee account email.']);
                }
            } else {
                $user = new User(['password' => Str::random(80)]);
            }
            if (User::where('email', $data['email'])->when($user->exists, fn ($q) => $q->whereKeyNot($user->id))->exists()) {
                throw ValidationException::withMessages(['email' => 'This work email is already used.']);
            }
            $wasExisting = $user->exists;
            $previousRole = $user->role_id;
            $changedEmail = $wasExisting && $user->email !== $data['email'];
            if ($data['account_status'] === 'active' && (! $wasExisting || ! $user->email_verified_at || $changedEmail || ! $user->canAuthenticate())) {
                throw ValidationException::withMessages(['account_status' => 'New or unverified accounts must use secure invitation activation.']);
            }
            $user->fill(['name' => $user->employee?->full_name ?? trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')), 'email' => $data['email']]);
            $user->forceFill(['role_id' => Role::SUPERVISOR]);
            $issued = null;
            if ($changedEmail) {
                $user->email_verified_at = null;
            }
            $user->account_status = $data['account_status'];
            $user->save();
            if (array_key_exists('supervisor_department_id', $data)) {
                $this->applyDepartment($user, $data['supervisor_department_id'] ? (int) $data['supervisor_department_id'] : null);
            }
            $this->accounts->invalidateSessions($user);
            $this->invitations->revoke($user);
            if ($user->account_status === 'pending_activation') {
                $issued = $this->invitations->issue($user);
            }
            $this->audit->record($new ? 'supervisor.created' : 'supervisor.updated', $user, ['previous_role_id' => $previousRole, 'role_id' => Role::SUPERVISOR, 'account_status' => $user->account_status, 'reused_identity' => $wasExisting]);
            $this->leave->refreshRouting();

            return compact('user', 'issued');
        });
    }

    public function assign(User $actor, User $target, array $data): SupervisorAssignment
    {
        return $this->transactions->run($actor, function (User $fresh) use ($target, $data) {
            abort_unless($fresh->isHr(), 403);
            $supervisor = $target->fresh();
            $employee = Employee::findOrFail($data['employee_id']);
            if (! $supervisor->supervisorDepartment || $supervisor->supervisorDepartment->status !== 'active' || (int) $supervisor->supervisor_department_id !== (int) $employee->department_id) {
                throw ValidationException::withMessages(['employee_id' => 'Assign an active department to this Supervisor and select an employee from that department.']);
            }
            if (! $supervisor->isSupervisor() || ! $supervisor->canAuthenticate() || $employee->user_id === $supervisor->id || $employee->employment_status !== 'active') {
                throw ValidationException::withMessages(['employee_id' => 'Choose an eligible employee and a different active Supervisor.']);
            }
            $overlaps = SupervisorAssignment::where('employee_id', $employee->id)->where('is_active', true)->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $data['effective_from']))->when(! empty($data['effective_until']), fn ($q) => $q->whereDate('effective_from', '<=', $data['effective_until']))->get();
            if ($overlaps->isNotEmpty() && empty($data['replace_existing'])) {
                throw ValidationException::withMessages(['employee_id' => 'This employee has a conflicting assignment. Explicitly confirm replacement to reassign.']);
            }
            foreach ($overlaps as $previous) {
                $effectiveFrom = Carbon::parse($data['effective_from'], config('attendance.timezone'));
                if ($effectiveFrom->toDateString() > now(config('attendance.timezone'))->toDateString() && $previous->effective_from->lt($effectiveFrom)) {
                    $oldUntil = $previous->effective_until?->toDateString();
                    $previous->update(['effective_until' => $effectiveFrom->subDay()->toDateString()]);
                    $this->audit->record('supervisor.assignment_shortened', $previous, ['previous_effective_until' => $oldUntil, 'effective_until' => $previous->effective_until->toDateString()]);

                    continue;
                }
                $previous->update(['is_active' => false, 'revoked_at' => now()]);
                $this->audit->record('supervisor.assignment_revoked', $previous);
            }
            $assignment = SupervisorAssignment::create(['employee_id' => $employee->id, 'supervisor_user_id' => $supervisor->id, 'effective_from' => $data['effective_from'], 'effective_until' => $data['effective_until'] ?? null, 'assigned_by_user_id' => $fresh->id]);
            $this->audit->record('supervisor.assigned', $assignment, ['employee_id' => $employee->id, 'supervisor_id' => $supervisor->id, 'replaced_ids' => $overlaps->modelKeys()]);
            $this->leave->refreshRouting($employee->id);

            return $assignment;
        });
    }

    public function setDepartment(User $actor, User $target, int $departmentId, string $version): void
    {
        $this->transactions->run($actor, function (User $fresh) use ($target, $departmentId, $version): void {
            abort_unless($fresh->isHr(), 403);
            $supervisor = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            abort_unless($supervisor->isSupervisor(), 404);
            EditVersion::check($supervisor, $version);
            $this->applyDepartment($supervisor, $departmentId);
            $this->leave->refreshRouting();
        });
    }

    private function applyDepartment(User $supervisor, ?int $departmentId): void
    {
        if ($departmentId !== null && ! Department::whereKey($departmentId)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['supervisor_department_id' => 'Please select a valid active department.']);
        }
        $conflict = SupervisorAssignment::where('supervisor_user_id', $supervisor->id)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', now(config('attendance.timezone'))->toDateString()))
            ->whereHas('employee', fn ($q) => $q->where('department_id', '!=', $departmentId))->exists();
        if ($conflict) {
            throw ValidationException::withMessages(['supervisor_department_id' => 'Resolve conflicting employee assignments before changing this department. Review history and explicitly revoke or reassign conflicts.']);
        }
        $previous = $supervisor->supervisor_department_id;
        if ((int) $previous === $departmentId) {
            return;
        }
        $supervisor->forceFill(['supervisor_department_id' => $departmentId])->save();
        $this->audit->record('supervisor.department_changed', $supervisor, ['previous_department_id' => $previous, 'department_id' => $departmentId]);
    }

    public function revoke(User $actor, User $target, SupervisorAssignment $assignment): void
    {
        $this->transactions->run($actor, function (User $fresh) use ($target, $assignment) {
            abort_unless($fresh->isHr(), 403);
            abort_unless($assignment->supervisor_user_id === $target->id, 404);
            $assignment = $assignment->fresh();
            abort_unless($assignment->is_active, 409);
            $assignment->update(['is_active' => false, 'revoked_at' => now()]);
            $this->audit->record('supervisor.assignment_revoked', $assignment);
            $this->leave->refreshRouting($assignment->employee_id);
        });
    }
}

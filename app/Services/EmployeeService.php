<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeService
{
    public function __construct(private Administration $administration, private InvitationService $invitations, private AuditService $audit, private AccountService $accounts) {}

    /** @return array{employee: Employee, issued: ?array} */
    public function save(array $data, ?Employee $employee = null): array
    {
        return $this->administration->run(function () use ($data, $employee) {
            $branch = Branch::whereKey($data['branch_id'])->where('status', 'active')->first();
            $department = Department::whereKey($data['department_id'])->where('status', 'active')->first();
            if (! $branch || ! $department) {
                throw ValidationException::withMessages(['branch_id' => 'Choose an active branch and department.']);
            }
            $new = ! $employee;
            if ($new) {
                $user = User::create(['name' => $data['first_name'].' '.$data['last_name'], 'email' => $data['work_email'], 'password' => Str::random(80)]);
                $employee = new Employee;
                $employee->user()->associate($user);
            } else {
                $employee = Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
                EditVersion::check($employee, $data['_edit_version'] ?? null);
                $user = $employee->user;
            }
            unset($data['_edit_version']);
            $oldEmail = $user->email;
            if ($oldEmail !== $data['work_email']) {
                DB::table('password_reset_tokens')->where('email', $oldEmail)->delete();
            }
            if (! $new && ! $user->isEmployee() && $oldEmail !== $data['work_email']) {
                throw ValidationException::withMessages(['work_email' => 'HR account email changes are not permitted through employee editing.']);
            }
            $oldBranchId = $employee->currentAssignment?->branch_id;
            $changed = array_keys(array_diff_assoc(collect($data)->except(['work_email', 'branch_id'])->all(), $employee->only(array_keys($data))));
            $employee->fill(collect($data)->except(['work_email', 'branch_id'])->all())->save();
            $user->fill(['name' => $employee->full_name, 'email' => $data['work_email']])->save();
            $issued = null;
            if ($new || $oldEmail !== $user->email) {
                $user->forceFill(['email_verified_at' => null, 'account_status' => 'pending_activation'])->save();
                $this->accounts->invalidateSessions($user);
                $issued = $this->invitations->issue($user);
            }
            if ($oldBranchId !== $branch->id) {
                $employee->assignments()->whereNull('effective_until')->update(['effective_until' => now()]);
                $employee->assignments()->create(['branch_id' => $branch->id, 'is_primary' => true, 'effective_from' => now()]);
                $this->audit->record('employee.branch_changed', $employee, ['previous_branch_id' => $oldBranchId, 'branch_id' => $branch->id]);
            }
            if ($employee->employment_status === 'inactive' && $user->account_status === 'active') {
                $this->accounts->deactivate($user);
            }
            $this->audit->record($new ? 'employee.created' : 'employee.updated', $employee, ['changed_fields' => $changed]);
            if (! $new && in_array('department_id', $changed, true)) {
                app(LeaveService::class)->refreshRouting($employee->id);
            }

            return compact('employee', 'issued');
        });
    }
}

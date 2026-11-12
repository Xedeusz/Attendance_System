<?php

namespace Tests\Support;

use App\Mail\AccountInvitationMail;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

trait Phase3Data
{
    protected function hr(): User
    {
        return User::factory()->create(['role_id' => Role::HR]);
    }

    protected function employeeData(array $overrides = []): array
    {
        return array_replace(['employee_number' => 'QA-'.bin2hex(random_bytes(4)), 'first_name' => 'Synthetic', 'last_name' => 'Person', 'work_email' => 'qa-'.bin2hex(random_bytes(5)).'@example.test', 'contact_number' => '+639171234567', 'branch_id' => Branch::factory()->create()->id, 'department_id' => Department::factory()->create()->id, 'position_title' => 'QA fixture', 'date_hired' => '2026-01-01', 'employment_status' => 'active'], $overrides);
    }

    protected function registerEmployee(array $data): Employee
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $this->post('/hr/employees', $data)->assertSessionHasNoErrors()->assertRedirect();

        return Employee::where('employee_number', $data['employee_number'])->firstOrFail();
    }

    protected function activationToken(): string
    {
        $mail = Mail::sent(AccountInvitationMail::class)->last();
        $this->assertNotNull($mail);
        preg_match('/#([a-f0-9]{64})/', $mail->render(), $matches);

        return $matches[1];
    }
}

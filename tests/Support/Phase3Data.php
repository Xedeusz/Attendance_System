<?php

namespace Tests\Support;

use App\Jobs\SendEssentialEmail;
use App\Mail\AccountInvitationMail;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EssentialEmail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

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
        Queue::fake([SendEssentialEmail::class]);
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.transport' => 'array']);
        $this->post('/hr/employees', $data)->assertSessionHasNoErrors()->assertRedirect();

        $this->runQueuedInvitations();

        return Employee::where('employee_number', $data['employee_number'])->firstOrFail();
    }

    protected function activationToken(): string
    {
        $this->runQueuedInvitations();
        $mail = Mail::sent(AccountInvitationMail::class)->last();
        $this->assertNotNull($mail);
        preg_match('/#([a-f0-9]{64})/', $mail->render(), $matches);

        return $matches[1];
    }

    private function runQueuedInvitations(): void
    {
        foreach (EssentialEmail::where('type', EssentialEmail::ACCOUNT_SETUP)->where('status', 'queued')->get() as $email) {
            app()->call([new SendEssentialEmail($email->id), 'handle']);
        }
    }
}

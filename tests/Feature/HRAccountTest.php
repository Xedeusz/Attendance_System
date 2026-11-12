<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Phase3Data;
use Tests\TestCase;

class HRAccountTest extends TestCase
{
    use Phase3Data, RefreshDatabase;

    public function test_hr_provisioning_requires_recent_password_confirmation(): void
    {
        $this->actingAs($this->hr());
        $this->post('/hr/hr-accounts', ['name' => 'New HR', 'email' => 'hr-new@example.test'])->assertRedirect(route('password.confirm'));
        $this->assertDatabaseCount('users', 1);
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $this->withSession(['auth.password_confirmed_at' => now()->timestamp])->post('/hr/hr-accounts', ['name' => 'New HR', 'email' => 'hr-new@example.test'])->assertSessionHasNoErrors();
        $new = User::where('email', 'hr-new@example.test')->firstOrFail();
        $this->assertTrue($new->isHr());
        $this->assertSame('pending_activation', $new->account_status);
        $this->assertDatabaseCount('account_invitations', 1);
    }

    public function test_last_active_hr_is_protected_and_other_hr_can_be_deactivated(): void
    {
        $hr = $this->hr();
        $this->actingAs($hr)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
        $this->patch('/hr/hr-accounts/'.$hr->id.'/status', ['action' => 'deactivate', 'confirmation' => 1])->assertSessionHasErrors('account');
        $this->assertSame('active', $hr->fresh()->account_status);
        $second = $this->hr();
        $this->patch('/hr/hr-accounts/'.$second->id.'/status', ['action' => 'deactivate', 'confirmation' => 1])->assertSessionHasNoErrors();
        $this->assertSame('deactivated', $second->fresh()->account_status);
        $this->assertSame(1, User::where('role_id', Role::HR)->where('account_status', 'active')->count());
    }

    public function test_bootstrap_is_private_and_single_use_without_default_credentials(): void
    {
        $this->get('/bootstrap-hr')->assertNotFound();
        $this->artisan('app:bootstrap-hr')->expectsQuestion('HR name', 'Synthetic HR')->expectsQuestion('HR email', 'bootstrap@example.test')->expectsQuestion('Password (12+ characters, mixed case, number and symbol)', 'Private!Bootstrap123')->expectsQuestion('Confirm password', 'Private!Bootstrap123')->assertSuccessful();
        $hr = User::sole();
        $this->assertTrue($hr->isHr());
        $this->assertSame('active', $hr->account_status);
        $this->artisan('app:bootstrap-hr')->expectsOutput('An HR account already exists. Use protected HR Account Management.')->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }
}

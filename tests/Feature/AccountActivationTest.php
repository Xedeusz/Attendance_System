<?php

namespace Tests\Feature;

use App\Models\AccountInvitation;
use App\Models\AuditLog;
use App\Models\LocalMailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Phase3Data;
use Tests\TestCase;

class AccountActivationTest extends TestCase
{
    use Phase3Data, RefreshDatabase;

    public function test_valid_single_use_invitation_activates_correct_account(): void
    {
        $this->actingAs($this->hr());
        $employee = $this->registerEmployee($this->employeeData());
        $token = $this->activationToken();
        $this->assertSame(hash('sha256', $token), AccountInvitation::sole()->token_hash);
        $this->post('/logout');
        $input = ['token' => $token, 'password' => 'Private!Activation123', 'password_confirmation' => 'Private!Activation123'];
        $this->postJson('/activate-account', $input)->assertOk();
        $this->assertSame('active', $employee->user->fresh()->account_status);
        $this->assertNotNull($employee->user->fresh()->email_verified_at);
        $this->assertTrue(Hash::check($input['password'], $employee->user->fresh()->password));
        $this->postJson('/activate-account', $input)->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->post('/login', ['email' => $employee->work_email, 'password' => $input['password']])->assertRedirect(route('employee.dashboard'));
        $this->assertNotNull($employee->user->fresh()->last_login_at);
        $this->assertStringNotContainsString($token, AuditLog::all()->toJson());
    }

    #[DataProvider('invalidStates')]
    public function test_invalid_invitation_states_never_activate(string $state): void
    {
        $this->actingAs($this->hr());
        $employee = $this->registerEmployee($this->employeeData());
        $token = $this->activationToken();
        $invitation = AccountInvitation::sole();
        match ($state) {
            'expired' => $invitation->update(['expires_at' => now()->subMinute()]),
            'revoked' => $invitation->update(['revoked_at' => now()]),
            'used' => $invitation->update(['used_at' => now()]),
            'deactivated' => $employee->user->forceFill(['account_status' => 'deactivated'])->save(),
            'inactive' => $employee->update(['employment_status' => 'inactive']),
            default => null,
        };
        $this->post('/logout');
        $this->postJson('/activate-account', ['token' => $token, 'password' => 'Private!Activation123', 'password_confirmation' => 'Private!Activation123'])->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertNotSame('active', $employee->user->fresh()->account_status);
    }

    public static function invalidStates(): array
    {
        return [['expired'], ['revoked'], ['used'], ['deactivated'], ['inactive']];
    }

    public function test_resend_replaces_previous_token_and_password_policy_is_enforced(): void
    {
        $this->actingAs($this->hr());
        $employee = $this->registerEmployee($this->employeeData());
        $old = $this->activationToken();
        $this->post('/hr/employees/'.$employee->id.'/invitation')->assertSessionHasNoErrors();
        $new = $this->activationToken();
        $this->assertNotSame($old, $new);
        $this->assertNotNull(AccountInvitation::orderBy('id')->first()->revoked_at);
        $this->post('/logout');
        $this->postJson('/activate-account', ['token' => $old, 'password' => 'Private!Activation123', 'password_confirmation' => 'Private!Activation123'])->assertJsonValidationErrors('token');
        $this->postJson('/activate-account', ['token' => $new, 'password' => 'weak', 'password_confirmation' => 'weak'])->assertJsonValidationErrors('password');
        $this->assertSame('pending_activation', $employee->user->fresh()->account_status);
    }

    public function test_local_capture_encrypts_tokens_and_requires_hr_reauthentication(): void
    {
        $this->app->instance('env', 'local');
        config(['mail.default' => 'private_local']);
        Mail::purge('private_local');
        $this->actingAs($this->hr());
        $data = $this->employeeData();
        $this->withSession(['_token' => 'qa-private-mail'])->post('/hr/employees', [...$data, '_token' => 'qa-private-mail'])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('status', fn ($status) => str_contains($status, 'no email was delivered externally'));
        $message = LocalMailMessage::sole();
        preg_match('/#([a-f0-9]{64})/', $message->payload['text'], $match);
        $this->assertArrayHasKey(1, $match);
        $this->assertStringNotContainsString($match[1], $message->getRawOriginal('payload'));
        $this->get('/hr/local-mail')->assertRedirect(route('password.confirm'));
        $this->withSession(['auth.password_confirmed_at' => now()->timestamp])->get('/hr/local-mail')->assertOk()->assertSee('Local Mail Inbox');
        $this->get('/hr/local-mail/'.$message->id)->assertOk()->assertSee('Private Mail Preview');
        $this->travel(25)->hours();
        $this->get('/hr/local-mail/'.$message->id)->assertRedirect(route('password.confirm'));
    }
}

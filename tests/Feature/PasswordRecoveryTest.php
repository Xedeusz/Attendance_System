<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['fortify.features' => [Features::resetPasswords()]]);
        require base_path('vendor/laravel/fortify/routes/routes.php');
        app('router')->getRoutes()->refreshNameLookups();
        app('router')->getRoutes()->refreshActionLookups();
    }

    public function test_recovery_uses_generic_responses_and_framework_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $known = $this->postJson('/forgot-password', ['email' => $user->email])->assertOk()->json('message');
        $unknown = $this->postJson('/forgot-password', ['email' => 'unknown@example.test'])->assertOk()->json('message');
        $this->assertSame($known, $unknown);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_token_is_single_use_and_updates_password(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        $input = ['email' => $user->email, 'token' => $token, 'password' => 'New!PrivatePassword456', 'password_confirmation' => 'New!PrivatePassword456'];
        $this->get('/reset-password/'.$token.'?email='.urlencode($user->email))->assertOk();
        $this->post('/reset-password', $input)->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check($input['password'], $user->fresh()->password));
        $this->post('/reset-password', $input)->assertSessionHasErrors('email');
    }

    public function test_expired_reset_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        $this->travel(61)->minutes();
        $this->post('/reset-password', ['email' => $user->email, 'token' => $token, 'password' => 'New!PrivatePassword456', 'password_confirmation' => 'New!PrivatePassword456'])->assertSessionHasErrors('email');
    }
}

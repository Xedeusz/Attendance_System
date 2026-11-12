<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_contains_no_signup_or_unconfigured_recovery_link(): void
    {
        $this->get('/login')->assertOk()->assertSee('Welcome back')->assertDontSee('Sign Up')->assertDontSee('Forgot password?');
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['email' => 'unauthorized@example.test'])->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
        $this->assertDatabaseCount('users', 0);
    }

    #[DataProvider('protectedPages')]
    public function test_workspace_pages_require_login(string $path): void
    {
        $this->get($path)->assertRedirect('/login');
    }

    public static function protectedPages(): array
    {
        return array_map(fn ($path) => [$path], ['/dashboard', '/employees', '/attendance', '/branches', '/leave', '/reports', '/notifications', '/profile', '/settings']);
    }

    public function test_invalid_credentials_are_rejected_without_authenticating(): void
    {
        User::factory()->create(['email' => 'person@example.test']);
        $this->post('/login', ['email' => 'person@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_valid_login_rotates_session_and_logout_revokes_authentication(): void
    {
        $user = User::factory()->create(['email' => 'person@example.test', 'password' => 'Private!Password123']);
        $this->get('/login');
        $session = session()->getId();
        $this->post('/login', ['email' => 'PERSON@example.test', 'password' => 'Private!Password123', 'remember' => 'on'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($session, session()->getId());
        $token = csrf_token();
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->assertNotSame($token, csrf_token());
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_is_throttled(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'throttle@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'throttle@example.test', 'password' => 'wrong'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_all_placeholder_pages_render_with_no_functional_business_actions(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (array_keys(config('workspace.modules')) as $module) {
            $this->get('/'.$module)->assertOk()->assertSee('structural preview')->assertSee('disabled', false);
            $this->post('/'.$module)->assertStatus(405);
        }
        $this->get('/settings')->assertOk()->assertSee('not available yet');
        $this->get('/profile')->assertOk()->assertSee('Read only');
    }

    public function test_dashboard_previews_do_not_report_invented_statistics(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['admin', 'hr', 'sub-admin', 'employee'] as $preview) {
            $this->get('/dashboard?view='.$preview)->assertOk()->assertSee('no roles assigned');
        }
        $this->get('/dashboard?view=employee')->assertSee('Time In')->assertSee('disabled', false)->assertSee('not available yet');
        $this->get('/dashboard?view=invalid')->assertNotFound();
    }

    public function test_passwords_are_hashed_and_no_default_user_is_seeded(): void
    {
        $user = User::factory()->create(['password' => 'Private!Password123']);
        $this->assertNotSame('Private!Password123', $user->password);
        $this->assertTrue(Hash::check('Private!Password123', $user->password));
        $this->seed();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_security_headers_and_no_cache_are_applied(): void
    {
        $this->get('/login')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_development_account_command_is_disabled_outside_local(): void
    {
        $this->artisan('app:provision-user')->expectsOutput('This development-only command is disabled outside the local environment.')->assertFailed();
    }

    public function test_user_content_is_escaped_in_the_workspace(): void
    {
        $this->actingAs(User::factory()->create(['name' => '<script>alert(1)</script>']));
        $this->get('/profile')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}

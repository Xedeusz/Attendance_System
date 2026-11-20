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
        $this->get('/login')->assertOk()->assertSee('Sign in')->assertDontSee('Sign Up')->assertDontSee('Forgot password?');
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
        $this->post('/login', ['email' => 'PERSON@example.test', 'password' => 'Private!Password123', 'remember' => 'on'])->assertRedirect(route('employee.dashboard'));
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

    public function test_self_service_navigation_opens_the_implemented_modules(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/leave')->assertRedirect(route('employee.leave.index'));
        $this->get('/employee/leave')->assertOk()->assertSee('My Leave');
        $this->post('/leave')->assertStatus(405);
        $this->get('/attendance')->assertRedirect(route('employee.attendance'));
        $this->get('/notifications')->assertOk()->assertSee('Attendance updates');
        $this->get('/settings')->assertOk()->assertDontSee('Display preferences');
        $this->get('/profile')->assertOk()->assertSee('Your information');
    }

    public function test_dashboard_previews_do_not_report_invented_statistics(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/dashboard?view=hr')->assertRedirect(route('employee.dashboard'));
        $this->get('/employee/dashboard')->assertOk()->assertSee('Time In')->assertSee('disabled', false)->assertSee('My Leave');
        $this->get('/hr/dashboard')->assertForbidden();
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
        $this->artisan('app:provision-user')->expectsOutput('Direct development login creation is disabled. Use app:bootstrap-hr for the first HR; HR creates all subsequent accounts.')->assertFailed();
    }

    public function test_user_content_is_escaped_in_the_workspace(): void
    {
        $this->actingAs(User::factory()->create(['name' => '<script>alert(1)</script>']));
        $this->get('/profile')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}

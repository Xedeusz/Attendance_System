<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Phase3Data;
use Tests\TestCase;

class Phase3AuthorizationTest extends TestCase
{
    use Phase3Data, RefreshDatabase;

    public function test_three_approved_roles_are_seeded_idempotently(): void
    {
        $this->seed();
        $this->seed();
        $this->assertSame(['hr', 'employee', 'supervisor'], Role::orderBy('id')->pluck('code')->all());
    }

    #[DataProvider('hrPages')]
    public function test_employee_is_forbidden_from_hr_pages(string $path): void
    {
        $this->actingAs(User::factory()->create())->get($path)->assertForbidden();
    }

    public static function hrPages(): array
    {
        return array_map(fn ($path) => [$path], ['/hr/dashboard', '/hr/employees', '/hr/employees/create', '/hr/branches', '/hr/departments', '/hr/hr-accounts', '/hr/audit-logs', '/hr/local-mail', '/reports', '/employees', '/branches']);
    }

    public function test_employee_cannot_mutate_other_profiles_or_promote_self(): void
    {
        $employee = Employee::factory()->create();
        $other = Employee::factory()->create();
        $this->actingAs($employee->user);
        $this->put('/hr/employees/'.$other->id, ['role_id' => Role::HR])->assertForbidden();
        $this->post('/hr/employees', [])->assertForbidden();
        $this->patch('/profile', ['contact_number' => '+639171234567', 'role_id' => Role::HR, 'branch_id' => 77])->assertSessionHasErrors(['role_id', 'branch_id']);
        $this->assertSame(Role::EMPLOYEE, $employee->user->fresh()->role_id);
        $this->get('/employee/profile/'.$other->id)->assertNotFound();
    }

    public function test_policy_limits_directory_access_and_hr_profile_uses_same_login(): void
    {
        $employee = Employee::factory()->create();
        $hr = $this->hr();
        $this->assertTrue(Gate::forUser($hr)->allows('create', Employee::class));
        $this->assertFalse(Gate::forUser($employee->user)->allows('create', Employee::class));
        $this->assertTrue(Gate::forUser($employee->user)->allows('view', $employee));
        $this->assertFalse(Gate::forUser($employee->user)->allows('update', $employee));
        $this->actingAs($hr)->get('/hr/dashboard')->assertOk()->assertSee('Workforce Overview');
        $this->get('/employee/dashboard')->assertOk()->assertSee('No employee profile');
    }

    public function test_pending_and_deactivated_users_cannot_login_or_use_existing_session(): void
    {
        foreach (['pending_activation', 'deactivated'] as $status) {
            $user = User::factory()->create(['account_status' => $status]);
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->actingAs($user)->get('/employee/dashboard')->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_employee_can_update_contact_but_password_change_requires_current_password(): void
    {
        $employee = Employee::factory()->create();
        $this->actingAs($employee->user);
        $this->patch('/employee/profile', ['contact_number' => '+639171112222'])->assertSessionHasNoErrors();
        $this->assertSame('+639171112222', $employee->fresh()->contact_number);
        $this->put('/user/password', ['current_password' => 'wrong', 'password' => 'New!Password123456', 'password_confirmation' => 'New!Password123456'])->assertSessionHasErrorsIn('updatePassword', 'current_password');
        $this->put('/user/password', ['current_password' => 'password', 'password' => 'New!Password123456', 'password_confirmation' => 'New!Password123456'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('New!Password123456', $employee->user->fresh()->password));
    }
}

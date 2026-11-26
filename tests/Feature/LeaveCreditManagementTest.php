<?php

namespace Tests\Feature;

use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveEntitlement;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class LeaveCreditManagementTest extends TestCase
{
    use AttendanceData, RefreshDatabase;

    public function test_only_the_canonical_sick_leave_balance_option_is_selectable_and_legacy_submission_is_rejected(): void
    {
        $employee = $this->attendee();
        $hr = $this->reviewer();
        $legacyType = LeaveType::factory()->create(['code' => '123', 'name' => 'Sick Leave', 'affects_balance' => false]);
        $canonicalType = LeaveType::factory()->create(['code' => 'SICK', 'name' => 'Sick Leave', 'affects_balance' => true]);
        LeavePolicy::factory()->create(['leave_type_id' => $canonicalType->id, 'allocation_basis' => 'annual', 'created_by_user_id' => $hr->id]);
        $balance = EmployeeLeaveBalance::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $canonicalType->id, 'available_days' => 15]);

        $this->actingAs($employee->user)->get(route('employee.leave.create'))
            ->assertOk()
            ->assertSee('Sick Leave')
            ->assertSee('15 days available')
            ->assertDontSee('Sick Leave — Available');

        $date = now(config('attendance.timezone'))->addWeek()->toDateString();
        $this->postJson(route('employee.leave.store'), [
            'leave_type_id' => $legacyType->id,
            'leave_category' => 'sick',
            'start_date' => $date,
            'end_date' => $date,
            'reason' => 'This direct request attempts to submit the duplicate legacy option.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('leave_type_id')
            ->assertJsonPath('errors.leave_type_id.0', 'Choose the current Sick Leave option. This duplicate legacy option is unavailable for new applications.');
        $this->assertDatabaseCount('leave_requests', 0);
        $this->assertDatabaseCount('leave_balance_transactions', 0);
        $this->assertSame(0, $employee->user->notifications()->count());

        $balance->update(['available_days' => 2]);
        $this->postJson(route('employee.leave.store'), [
            'leave_type_id' => $canonicalType->id,
            'leave_category' => 'sick',
            'start_date' => $date,
            'end_date' => now(config('attendance.timezone'))->addWeek()->addDays(2)->toDateString(),
            'reason' => 'This request exceeds the canonical Sick Leave balance.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('requested_duration')
            ->assertJsonPath('errors.requested_duration.0', 'Cannot submit this leave application. You requested 3 days, but only 2 days are available.');
        $this->assertDatabaseCount('leave_requests', 0);
        $this->assertDatabaseCount('leave_balance_transactions', 0);
    }

    public function test_application_form_disables_exhausted_annual_leave_and_requires_event_eligibility(): void
    {
        $employee = $this->attendee();
        $employee->update(['sex' => 'male']);
        $hr = $this->reviewer();
        $vacation = LeaveType::factory()->create(['code' => 'VACATION', 'name' => 'Vacation Leave', 'affects_balance' => true]);
        $sick = LeaveType::factory()->create(['code' => 'SICK', 'name' => 'Sick Leave', 'affects_balance' => true]);
        $paternity = LeaveType::factory()->create(['code' => 'PATERNITY', 'name' => 'Paternity Leave', 'affects_balance' => true]);
        foreach ([[$vacation, 'annual'], [$sick, 'annual'], [$paternity, 'event']] as [$type, $basis]) {
            LeavePolicy::factory()->create(['leave_type_id' => $type->id, 'allocation_basis' => $basis, 'created_by_user_id' => $hr->id]);
        }
        EmployeeLeaveBalance::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $vacation->id, 'available_days' => 0]);
        EmployeeLeaveBalance::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $sick->id, 'available_days' => 5]);

        $response = $this->actingAs($employee->user)->get(route('employee.leave.create'))->assertOk();

        $response
            ->assertSee('Vacation Leave — 0 days available (Unavailable)')
            ->assertSee('You have no available Vacation Leave credits remaining.')
            ->assertSee('Sick Leave')
            ->assertSee('5 days available')
            ->assertSee('Paternity Leave — Eligibility verification required')
            ->assertSee('Paternity Leave requires HR eligibility verification');
        $this->assertStringContainsString('value="vacation"', $response->getContent());
        $this->assertMatchesRegularExpression('/value="vacation"[^>]*disabled/', $response->getContent());
    }

    public function test_zero_or_insufficient_credits_reject_tampered_submissions_without_side_effects(): void
    {
        $employee = $this->attendee();
        $type = LeaveType::factory()->create(['code' => 'VACATION', 'name' => 'Vacation Leave', 'affects_balance' => true]);
        $balance = EmployeeLeaveBalance::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'available_days' => 0]);
        $start = now(config('attendance.timezone'))->addWeek()->toDateString();
        $payload = [
            'leave_type_id' => $type->id,
            'leave_category' => 'vacation',
            'start_date' => $start,
            'end_date' => $start,
            'reason' => 'This direct request attempts to bypass the disabled leave choice.',
        ];

        $this->actingAs($employee->user)->postJson(route('employee.leave.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('leave_type_id')
            ->assertJsonPath('errors.leave_type_id.0', 'Cannot submit this leave application because your available Vacation Leave balance is 0 days.');
        $this->assertDatabaseCount('leave_requests', 0);
        $this->assertDatabaseCount('leave_balance_transactions', 0);
        $this->assertSame(0, $employee->user->notifications()->count());

        $balance->update(['available_days' => 2]);
        $this->postJson(route('employee.leave.store'), array_replace($payload, [
            'end_date' => now(config('attendance.timezone'))->addWeek()->addDays(3)->toDateString(),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('requested_duration')
            ->assertJsonPath('errors.requested_duration.0', 'Cannot submit this leave application. You requested 4 days, but only 2 days are available.');
        $this->assertDatabaseCount('leave_requests', 0);
        $this->assertDatabaseCount('leave_balance_transactions', 0);
        $this->assertSame(0, $employee->user->notifications()->count());
    }

    public function test_pending_reservations_disable_a_type_and_hr_adjustment_restores_it(): void
    {
        $employee = $this->attendee();
        $hr = $this->reviewer();
        $type = LeaveType::factory()->create(['code' => 'VACATION', 'name' => 'Vacation Leave', 'affects_balance' => true]);
        LeavePolicy::factory()->create(['leave_type_id' => $type->id, 'allocation_basis' => 'annual', 'created_by_user_id' => $hr->id]);
        EmployeeLeaveBalance::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'available_days' => 2]);
        LeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => now(config('attendance.timezone'))->addWeek(),
            'end_date' => now(config('attendance.timezone'))->addWeek()->addDay(),
            'requested_duration' => 2,
            'reserved_duration' => 2,
            'status' => 'pending_supervisor_approval',
        ]);

        $this->actingAs($employee->user)->get(route('employee.leave.create'))
            ->assertOk()
            ->assertSee('0 days available (Reserved)')
            ->assertSee('Your remaining Vacation Leave credits are currently reserved by pending applications.');

        app(LeaveBalanceService::class)->adjust($hr, $employee, $type, now(config('attendance.timezone'))->format('Y'), 3, 'Authorized additional credits for the regression test.');

        $this->get(route('employee.leave.create'))
            ->assertOk()
            ->assertSee('3 days available')
            ->assertDontSee('0 days available (Reserved)');
    }

    public function test_employee_credit_page_hides_zero_decimal_places_but_keeps_half_days(): void
    {
        $employee = $this->attendee();
        $type = LeaveType::factory()->create(['name' => 'Vacation Leave', 'affects_balance' => true]);
        $balance = EmployeeLeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'policy_period' => now(config('attendance.timezone'))->format('Y'),
            'available_days' => 1,
        ]);
        $hr = $this->reviewer();
        LeaveBalanceTransaction::factory()->create([
            'employee_leave_balance_id' => $balance->id,
            'authorized_by_user_id' => $hr->id,
            'transaction_type' => 'historical_approved_debit',
            'amount' => -2,
        ]);
        LeaveBalanceTransaction::factory()->create([
            'employee_leave_balance_id' => $balance->id,
            'authorized_by_user_id' => $hr->id,
            'amount' => 0.5,
        ]);

        $response = $this->actingAs($employee->user)->get(route('employee.leave.index'))->assertOk();

        $response
            ->assertSee('class="text-2xl font-bold text-slate-900">1</span>', false)
            ->assertSee('class="font-semibold">3</dd>', false)
            ->assertSee('class="font-semibold">2</dd>', false)
            ->assertSee('class="font-semibold">0</dd>', false)
            ->assertSee('class="font-semibold text-emerald-700">+0.5</td>', false)
            ->assertSee('class="font-semibold text-red-700">-2</td>', false)
            ->assertDontSee('class="font-semibold text-red-700">-2.0</td>', false);
    }

    public function test_annual_allocation_is_idempotent_and_posts_one_credit_entry(): void
    {
        $employee = $this->attendee();
        $hr = $this->reviewer();
        $type = LeaveType::factory()->create(['code' => 'VACATION-TEST', 'affects_balance' => true]);
        LeavePolicy::factory()->create(['leave_type_id' => $type->id, 'default_days' => 15]);

        $this->artisan('leave:allocate-annual', ['year' => 2026, '--actor' => $hr->id])->assertSuccessful();
        $this->artisan('leave:allocate-annual', ['year' => 2026, '--actor' => $hr->id])->assertSuccessful();

        $this->assertDatabaseCount('leave_entitlements', 1);
        $this->assertDatabaseHas('employee_leave_balances', ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'policy_period' => '2026', 'available_days' => 15]);
        $this->assertDatabaseHas('leave_balance_transactions', ['transaction_type' => 'annual_allocation', 'amount' => 15]);
    }

    public function test_pending_requests_reserve_credits_without_posting_a_debit(): void
    {
        $employee = $this->attendee();
        $type = LeaveType::factory()->create(['affects_balance' => true]);
        EmployeeLeaveBalance::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'policy_period' => '2026', 'available_days' => 10]);
        LeaveRequest::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'start_date' => '2026-12-01', 'end_date' => '2026-12-04', 'requested_duration' => 4, 'reserved_duration' => 4, 'status' => 'pending_supervisor_approval']);

        $summary = app(LeaveBalanceService::class)->summary($employee, $type, '2026');

        $this->assertSame(4.0, $summary['pending']);
        $this->assertSame(6.0, $summary['available']);
        $this->assertDatabaseEmpty('leave_balance_transactions');
    }

    public function test_adjustment_cannot_reduce_credits_below_pending_reservations(): void
    {
        $employee = $this->attendee();
        $hr = $this->reviewer();
        $type = LeaveType::factory()->create(['affects_balance' => true]);
        EmployeeLeaveBalance::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'policy_period' => '2026', 'available_days' => 5]);
        LeaveRequest::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'start_date' => '2026-12-01', 'end_date' => '2026-12-04', 'reserved_duration' => 4, 'status' => 'pending_supervisor_approval']);

        $this->expectException(ValidationException::class);
        app(LeaveBalanceService::class)->adjust($hr, $employee, $type, '2026', -2, 'Authorized test deduction that conflicts with a reservation.');
    }

    public function test_revocation_appends_a_reversal_and_restores_the_projection(): void
    {
        $employee = $this->attendee();
        $supervisor = User::factory()->create(['role_id' => Role::SUPERVISOR]);
        $hr = $this->reviewer();
        $type = LeaveType::factory()->create(['affects_balance' => true]);
        $balance = EmployeeLeaveBalance::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'policy_period' => '2026', 'available_days' => 10]);
        $leave = LeaveRequest::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'start_date' => '2026-12-01', 'end_date' => '2026-12-03', 'requested_duration' => 3, 'reserved_duration' => 3, 'status' => 'approved']);
        $service = app(LeaveBalanceService::class);

        $debit = $service->debitApproval($leave, $supervisor, 3);
        $this->assertSame('7.00', $balance->fresh()->available_days);
        $reversal = $service->reverseApproval($leave, $hr, 'HR revoked the approved leave after documented review.');

        $this->assertSame($debit->id, $reversal?->reversal_of_transaction_id);
        $this->assertSame('10.00', $balance->fresh()->available_days);
        $this->assertDatabaseCount('leave_balance_transactions', 2);
        $this->assertDatabaseHas('leave_balance_transactions', ['transaction_type' => 'approval_reversal', 'amount' => 3]);
    }

    public function test_hr_can_grant_verified_event_entitlement_and_employee_cannot(): void
    {
        $employee = $this->attendee();
        $hr = $this->reviewer();
        $type = LeaveType::factory()->create(['code' => 'MATERNITY-TEST', 'affects_balance' => true]);
        $policy = LeavePolicy::factory()->create(['leave_type_id' => $type->id, 'allocation_basis' => 'event', 'default_days' => 105, 'day_basis' => 'calendar_days', 'eligibility_rules' => ['standard_days' => 105, 'solo_parent_days' => 120, 'miscarriage_or_emergency_termination_days' => 60]]);
        $payload = ['employee_id' => $employee->id, 'leave_policy_id' => $policy->id, 'event_date' => '2026-11-10', 'event_variant' => 'standard', 'verification_notes' => 'HR reviewed the submitted eligibility documents for this event.', 'confirmation' => 1];

        $this->actingAs($employee->user)->withSession(['auth.password_confirmed_at' => time()])->post(route('hr.leave.event-entitlements.store'), $payload)->assertForbidden();
        $this->actingAs($hr)->withSession(['auth.password_confirmed_at' => time()])->post(route('hr.leave.event-entitlements.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();

        $entitlement = LeaveEntitlement::sole();
        $this->assertSame('verified', $entitlement->eligibility_status);
        $this->assertSame('105.00', $entitlement->granted_days);
        $this->assertDatabaseHas('leave_balance_transactions', ['leave_entitlement_id' => $entitlement->id, 'transaction_type' => 'event_grant', 'amount' => 105]);
    }

    public function test_hr_credit_export_contains_reservations_and_availability_columns(): void
    {
        $employee = $this->attendee();
        $employee->update(['employee_number' => '=1+1']);
        $hr = $this->reviewer();
        $type = LeaveType::factory()->create(['affects_balance' => true]);
        EmployeeLeaveBalance::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'policy_period' => '2026', 'available_days' => 8]);

        $response = $this->actingAs($hr)->get(route('hr.leave.balances.export', ['year' => 2026]))->assertOk();
        $content = $response->streamedContent();

        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('"Total Entitlement","Approved Days","Pending Days","Remaining Days","Available for Request"', $content);
        $this->assertStringContainsString($employee->employee_number, $content);
        $this->assertStringContainsString("'=1+1", $content);
        $this->assertStringContainsString(',8,0,0,8,8', $content);
        $this->assertStringNotContainsString(',8.0,', $content);
        $this->assertStringNotContainsString(',8.00,', $content);
        $this->assertSame(0, LeaveBalanceTransaction::count());
    }

    public function test_historical_approved_legacy_leave_is_reconciled_to_the_canonical_balance_once(): void
    {
        $employee = $this->attendee();
        $hr = $this->reviewer();
        $canonicalType = LeaveType::factory()->create(['code' => 'SICK', 'name' => 'Sick Leave', 'affects_balance' => true]);
        $legacyType = LeaveType::factory()->create(['code' => '123', 'name' => 'Sick Leave', 'affects_balance' => false]);
        $balance = EmployeeLeaveBalance::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $canonicalType->id, 'policy_period' => '2026', 'available_days' => 15]);
        LeaveBalanceTransaction::factory()->create([
            'employee_leave_balance_id' => $balance->id,
            'authorized_by_user_id' => $hr->id,
            'transaction_type' => 'annual_allocation',
            'amount' => 15,
            'reason' => 'Annual test allocation.',
        ]);
        $leave = LeaveRequest::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $legacyType->id,
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-21',
            'requested_duration' => 2,
            'charged_duration' => 0,
            'type_snapshot' => ['name' => 'Sick Leave', 'affects_balance' => false],
            'status' => 'approved',
        ]);

        $this->artisan('leave:reconcile-credits', ['--apply' => true, '--actor' => $hr->id])->assertSuccessful();
        $this->artisan('leave:reconcile-credits', ['--apply' => true, '--actor' => $hr->id])->assertSuccessful();

        $this->assertSame('13.00', $balance->fresh()->available_days);
        $this->assertSame($legacyType->id, $leave->fresh()->leave_type_id);
        $this->assertDatabaseHas('leave_balance_transactions', [
            'employee_leave_balance_id' => $balance->id,
            'leave_request_id' => $leave->id,
            'transaction_type' => 'historical_approved_debit',
            'amount' => -2,
        ]);
        $this->assertSame(1, LeaveBalanceTransaction::where('leave_request_id', $leave->id)->count());
        $summary = app(LeaveBalanceService::class)->summary($employee, $canonicalType, '2026');
        $this->assertSame(2.0, $summary['approved']);
        $this->assertSame(13.0, $summary['available']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveEntitlement;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\SpecialLeaveEligibilityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class SpecialLeaveEligibilityTest extends TestCase
{
    use AttendanceData, RefreshDatabase;

    public function test_employee_submits_private_verification_request_and_other_employee_cannot_access_it(): void
    {
        Storage::fake('leave');
        $employee = $this->attendee();
        $hr = $this->reviewer();
        $type = LeaveType::factory()->create(['code' => 'MATERNITY', 'name' => 'Maternity Leave', 'affects_balance' => true]);
        LeavePolicy::factory()->create(['leave_type_id' => $type->id, 'allocation_basis' => 'event', 'default_days' => 105, 'day_basis' => 'calendar_days']);
        $document = UploadedFile::fake()->createWithContent('eligibility.pdf', "%PDF-1.4\n%%EOF");

        $this->actingAs($employee->user)->post(route('employee.leave.eligibility.store'), ['leave_type_id' => $type->id, 'event_category' => 'actual_live_childbirth', 'actual_event_date' => '2026-11-20', 'additional_information' => 'Event information supplied for authorized HR eligibility review.', 'supporting_documents' => [$document], 'employee_declaration' => 1])->assertRedirect()->assertSessionHasNoErrors();

        $request = SpecialLeaveEligibilityRequest::with('documents')->sole();
        $this->assertSame('pending_hr_review', $request->status);
        $this->assertCount(1, $request->documents);
        Storage::disk('leave')->assertExists($request->documents->first()->storage_path);
        $this->assertNotSame("%PDF-1.4\n%%EOF", Storage::disk('leave')->get($request->documents->first()->storage_path));
        $this->assertSame("%PDF-1.4\n%%EOF", Crypt::decryptString(Storage::disk('leave')->get($request->documents->first()->storage_path)));
        $this->assertTrue($hr->notifications()->where('data->title', 'A special leave eligibility request needs HR review.')->exists());
        $other = $this->attendee();
        $this->actingAs($other->user)->get(route('employee.leave.eligibility.show', $request))->assertForbidden();
        $this->get(route('employee.leave.eligibility.document', $request->documents->first()))->assertForbidden();
    }

    public function test_hr_requests_information_employee_resubmits_and_verification_activates_one_entitlement(): void
    {
        Storage::fake('leave');
        $employee = $this->attendee();
        $hr = $this->reviewer();
        $type = LeaveType::factory()->create(['code' => 'MATERNITY', 'name' => 'Maternity Leave', 'affects_balance' => true]);
        LeavePolicy::factory()->create(['leave_type_id' => $type->id, 'allocation_basis' => 'event', 'default_days' => 105, 'day_basis' => 'calendar_days', 'eligibility_rules' => ['standard_days' => 105, 'solo_parent_days' => 120, 'miscarriage_or_emergency_termination_days' => 60]]);
        $request = SpecialLeaveEligibilityRequest::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'actual_event_date' => '2026-11-20']);

        $this->actingAs($hr)->withSession(['auth.password_confirmed_at' => time()])->post(route('hr.leave.eligibility.review', $request), ['decision' => 'additional_information_required', 'remarks' => 'Please provide the updated event documentation for verification.', 'confirmation' => 1])->assertRedirect();
        $this->assertSame('additional_information_required', $request->fresh()->status);

        $this->actingAs($employee->user)->put(route('employee.leave.eligibility.update', $request), ['event_category' => 'live_childbirth_solo_parent', 'actual_event_date' => '2026-11-20', 'additional_information' => 'Updated event information and the requested supporting explanation.', 'employee_declaration' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('pending_hr_review', $request->fresh()->status);

        $this->actingAs($hr)->withSession(['auth.password_confirmed_at' => time()])->post(route('hr.leave.eligibility.review', $request), ['decision' => 'verified', 'remarks' => 'HR verified the applicable event entitlement and supporting information.', 'confirmation' => 1])->assertRedirect();

        $entitlement = LeaveEntitlement::sole();
        $this->assertSame('120.00', $entitlement->granted_days);
        $this->assertSame($request->id, $entitlement->special_leave_eligibility_request_id);
        $this->assertSame('120.00', EmployeeLeaveBalance::sole()->available_days);
        $this->assertSame(1, LeaveBalanceTransaction::where('transaction_type', 'event_grant')->count());
        $this->assertSame(3, $request->activities()->count());

        $this->postJson(route('hr.leave.eligibility.review', $request), ['decision' => 'verified', 'remarks' => 'A repeated verification must not create another entitlement.', 'confirmation' => 1])->assertConflict();
        $this->assertSame(1, LeaveEntitlement::count());
        $this->assertSame(1, LeaveBalanceTransaction::count());
    }

    public function test_paternity_verification_grants_seven_working_days_and_supervisor_cannot_decide_eligibility(): void
    {
        $employee = $this->attendee();
        $hr = $this->reviewer();
        $type = LeaveType::factory()->create(['code' => 'PATERNITY', 'name' => 'Paternity Leave', 'affects_balance' => true]);
        LeavePolicy::factory()->create(['leave_type_id' => $type->id, 'allocation_basis' => 'event', 'default_days' => 7, 'day_basis' => 'working_days', 'eligibility_rules' => ['standard_days' => 7]]);
        $request = SpecialLeaveEligibilityRequest::factory()->create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'event_category' => 'paternity_qualifying_event', 'actual_event_date' => '2026-11-20']);

        $supervisor = User::factory()->create(['role_id' => Role::SUPERVISOR]);
        $this->actingAs($supervisor)->post(route('hr.leave.eligibility.review', $request), ['decision' => 'verified', 'remarks' => 'A Supervisor must not perform HR eligibility verification.', 'confirmation' => 1])->assertForbidden();
        $this->actingAs($hr)->withSession(['auth.password_confirmed_at' => time()])->post(route('hr.leave.eligibility.review', $request), ['decision' => 'verified', 'remarks' => 'HR verified the qualifying paternity event and supporting information.', 'confirmation' => 1])->assertRedirect();

        $this->assertSame('7.00', LeaveEntitlement::sole()->granted_days);
        $this->actingAs($employee->user)->get(route('employee.leave.create'))->assertOk()->assertSee('7 days available');
    }
}

<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceHoliday;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\HolidayImport;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\SupervisorAssignment;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleException;
use App\Services\AttendancePolicyResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class AttendancePolicyTest extends TestCase
{
    use AttendanceData, RefreshDatabase;

    private function scheduleInput(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Regular office shift', 'scope' => 'company', 'starts_at' => '08:00', 'ends_at' => '16:00',
            'break_starts_at' => '12:00', 'break_ends_at' => '13:00', 'paid_break' => 0, 'grace_minutes' => 10,
            'grace_method' => 'from_start', 'working_days' => [1, 2, 3, 4, 5], 'effective_from' => '2026-11-19',
        ], $overrides);
    }

    private function holidayInput(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Configured holiday', 'holiday_date' => '2026-11-19', 'status' => 'active', 'type' => 'regular',
            'work_expected' => 0, 'scope' => 'company',
        ], $overrides);
    }

    private function manila(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-11-19 '.$time, 'Asia/Manila');
    }

    public function test_hr_can_configure_schedules_and_same_scope_overlaps_are_rejected(): void
    {
        $this->travelTo($this->manila('07:00'));
        $this->actingAs($this->reviewer());
        $this->get(route('hr.schedules.create'))->assertOk()->assertSeeText('Expected net work');
        $this->post(route('hr.schedules.store'), $this->scheduleInput())->assertRedirectToRoute('hr.schedules.index');
        $this->post(route('hr.schedules.store'), $this->scheduleInput(['name' => 'Conflicting schedule']))
            ->assertSessionHasErrors('effective_from');
        $this->assertDatabaseCount('work_schedules', 1);
        $this->get(route('hr.schedules.index'))->assertOk()->assertSeeText('Regular office shift');
    }

    public function test_schedule_assignment_priority_is_explicit_and_versions_preserve_the_original_punch(): void
    {
        $this->travelTo($this->manila('08:25'));
        $employee = $this->attendee();
        $branch = $employee->currentAssignment->branch;
        $hr = $this->reviewer();
        WorkSchedule::factory()->create(['created_by' => $hr->id, 'name' => 'Company']);
        WorkSchedule::factory()->create(['created_by' => $hr->id, 'branch_id' => $branch->id, 'name' => 'Branch']);
        WorkSchedule::factory()->create(['created_by' => $hr->id, 'department_id' => $employee->department_id, 'name' => 'Department']);
        $resolver = app(AttendancePolicyResolver::class);
        $this->assertSame('Department', $resolver->resolve($employee, '2026-11-19', $branch)['schedule']['name']);
        $schedule = WorkSchedule::factory()->create(['created_by' => $hr->id, 'employee_id' => $employee->id, 'name' => 'Original employee shift']);

        $this->punch($this->capture($employee))->assertOk();
        $record = AttendanceRecord::sole();
        $this->assertTrue($record->computation->incomplete);
        $this->assertNull($record->computation->undertime_minutes);
        $this->actingAs($hr)->put(route('hr.schedules.update', $schedule), $this->scheduleInput([
            'scope' => 'employee', 'employee_id' => $employee->id, 'starts_at' => '08:30', 'name' => 'New employee shift',
        ]))->assertRedirectToRoute('hr.schedules.index');
        $this->travelTo($this->manila('15:30'));
        $this->punch($this->capture($employee, 'time_out'), color: 180)->assertOk();

        $record->refresh();
        $this->assertSame('New employee shift', $resolver->resolve($employee, '2026-11-19', $branch)['schedule']['name']);
        $this->assertSame($schedule->id, $record->computation->work_schedule_id);
        $this->assertSame(365, $record->computation->net_minutes);
        $this->assertSame(25, $record->computation->late_minutes);
        $this->assertSame(30, $record->computation->undertime_minutes);
        $this->assertDatabaseCount('attendance_photo_evidence', 2);
        $this->get(route('attendance.show', $record))->assertOk()->assertSeeText(['Working hours breakdown', 'Original employee shift']);
        $this->actingAs($this->attendee()->user)->get(route('attendance.show', $record))->assertForbidden();
    }

    #[TestWith(['regular', false, false, 'not_scheduled'])]
    #[TestWith(['regular', false, true, 'work'])]
    #[TestWith(['special_non_working', false, false, 'not_scheduled'])]
    #[TestWith(['special_working', true, false, 'work'])]
    #[TestWith(['company', false, false, 'not_scheduled'])]
    public function test_holiday_work_expectation_is_independent_of_the_label(string $type, bool $workExpected, bool $exception, string $obligation): void
    {
        $this->travelTo($this->manila('08:25'));
        $employee = $this->attendee();
        $schedule = WorkSchedule::factory()->create();
        AttendanceHoliday::factory()->create(['holiday_date' => '2026-11-19', 'type' => $type, 'work_expected' => $workExpected]);
        if ($exception) {
            WorkScheduleException::factory()->create([
                'employee_id' => $employee->id, 'work_date' => '2026-11-19', 'kind' => 'shift', 'work_schedule_id' => $schedule->id,
            ]);
        }

        $this->punch($this->capture($employee))->assertOk();

        $calculation = AttendanceRecord::sole()->computation;
        $this->assertSame($obligation, $calculation->policy['obligation']);
        $this->assertSame([$type], $calculation->holiday_types);
        $this->assertSame($obligation === 'work' ? 420 : 0, $calculation->expected_minutes);
        $this->assertSame($obligation === 'work' ? 25 : null, $calculation->late_minutes);
    }

    public function test_effective_branch_and_location_coverage_preserve_overlapping_holiday_declarations(): void
    {
        $this->travelTo($this->manila('07:00'));
        $employee = $this->attendee();
        $local = $employee->currentAssignment->branch;
        $local->update(['city' => 'Lipa', 'province' => 'Batangas']);
        $effectiveDate = $this->manila('00:00')->addDay();
        $employee->currentAssignment->update(['effective_until' => $effectiveDate->utc()]);
        $other = Branch::factory()->create(['city' => 'Tagaytay', 'province' => 'Cavite']);
        $employee->assignments()->create(['branch_id' => $other->id, 'is_primary' => true, 'effective_from' => $effectiveDate->utc()]);
        WorkSchedule::factory()->create();
        foreach (['2026-11-19', '2026-11-20'] as $date) {
            AttendanceHoliday::factory()->create(['holiday_date' => $date, 'type' => 'local', 'scope' => 'locations', 'locations' => ['lipa | batangas']]);
            AttendanceHoliday::factory()->create(['holiday_date' => $date, 'type' => 'special_working', 'work_expected' => true]);
        }
        $resolver = app(AttendancePolicyResolver::class);

        $days = $resolver->forPeriod($employee->newCollection([$employee]), $effectiveDate->subDay(), $effectiveDate)[$employee->id];

        $this->assertSame(['local', 'special_working'], array_column($days['2026-11-19']['holidays'], 'type'));
        $this->assertSame('not_scheduled', $days['2026-11-19']['obligation']);
        $this->assertSame(['special_working'], array_column($days['2026-11-20']['holidays'], 'type'));
        $this->assertSame('work', $days['2026-11-20']['obligation']);
    }

    public function test_hr_can_open_holiday_management_before_any_holidays_are_configured(): void
    {
        $this->travelTo($this->manila('07:00'));

        $this->actingAs($this->reviewer())->get(route('hr.holidays.index'))
            ->assertOk()
            ->assertSeeText('No holidays match this view');
    }

    public function test_nonworking_holiday_without_attendance_appears_in_employee_and_hr_views_without_creating_punches(): void
    {
        $this->travelTo($this->manila('07:00'));
        $employee = $this->attendee();
        WorkSchedule::factory()->create();
        AttendanceHoliday::factory()->create(['name' => 'Branch Closure Day', 'holiday_date' => '2026-11-19', 'type' => 'local', 'scope' => 'branches'])
            ->branches()->attach($employee->currentAssignment->branch_id);
        AttendanceHoliday::factory()->create(['name' => 'Other Branch Holiday', 'holiday_date' => '2026-11-19', 'type' => 'local', 'scope' => 'branches'])
            ->branches()->attach(Branch::factory()->create()->id);

        $this->actingAs($employee->user)->get(route('employee.dashboard'))->assertOk()->assertSeeText(['Branch Closure Day', 'Holiday · No Work Scheduled']);
        $this->get(route('employee.work-calendar', ['month' => '2026-11']))->assertOk()->assertSeeText(['Branch Closure Day', 'Local holiday'])->assertDontSeeText('Other Branch Holiday');
        $this->actingAs($this->reviewer())->get(route('hr.dashboard'))->assertOk()->assertViewHas('dailyPolicyStats', fn (array $stats) => $stats['Employees not scheduled to work'] === 1 && $stats['Employees present'] === 0 && $stats['Applicable holidays today'] === 1);
        $this->get(route('hr.holidays.index', ['year' => 2026, 'month' => 11, 'view' => 'month', 'branch_id' => $employee->currentAssignment->branch_id]))
            ->assertOk()->assertSeeText('Branch Closure Day')->assertDontSeeText('Other Branch Holiday');
        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_provincial_scope_covers_other_cities_in_the_province(): void
    {
        $branch = Branch::factory()->create(['city' => 'Lipa', 'province' => 'Batangas']);
        $neighbor = Branch::factory()->create(['city' => 'Tanauan', 'province' => 'Batangas']);
        $outside = Branch::factory()->create(['city' => 'Tagaytay', 'province' => 'Cavite']);

        $this->actingAs($this->reviewer())->post(route('hr.holidays.store'), $this->holidayInput([
            'name' => 'Provincial observance', 'type' => 'local', 'scope' => 'locations', 'locations' => ['province:batangas'],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $holiday = AttendanceHoliday::sole();
        $this->assertTrue($holiday->appliesTo($branch->id, AttendancePolicyResolver::location($branch)));
        $this->assertTrue($holiday->appliesTo($neighbor->id, AttendancePolicyResolver::location($neighbor)));
        $this->assertFalse($holiday->appliesTo($outside->id, AttendancePolicyResolver::location($outside)));
    }

    public function test_work_calendar_shows_weekly_leave_segments_and_authorized_overlapping_date_details(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-30 08:25', 'Asia/Manila'));
        $employee = $this->attendee();
        $other = $this->attendee();
        WorkSchedule::factory()->create(['employee_id' => $employee->id, 'name' => 'Assigned office hours']);
        $leave = LeaveRequest::factory()->create([
            'employee_id' => $employee->id, 'status' => 'approved', 'start_date' => '2026-11-26',
            'end_date' => '2026-12-02', 'requested_duration' => 7,
        ]);
        $privateLeave = LeaveRequest::factory()->create([
            'employee_id' => $other->id, 'status' => 'approved', 'start_date' => '2026-11-30', 'end_date' => '2026-12-02',
        ]);
        foreach (['regular', 'special_non_working', 'special_working', 'company'] as $type) {
            AttendanceHoliday::factory()->create([
                'holiday_date' => '2026-11-30', 'type' => $type, 'name' => 'Calendar '.$type, 'work_expected' => $type === 'special_working',
            ]);
        }
        $this->punch($this->capture($other))->assertOk();
        $otherRecord = AttendanceRecord::sole();
        $this->punch($this->capture($employee))->assertOk();
        $record = AttendanceRecord::where('employee_id', $employee->id)->sole();

        $calendar = $this->get(route('employee.work-calendar', ['month' => '2026-11']))->assertOk()->assertSee('aria-current="date"', false);
        $this->assertSame(2, substr_count($calendar->getContent(), 'data-leave-segment="'.$leave->id.'"'));
        $calendar->assertDontSee('data-leave-segment="'.$privateLeave->id.'"', false);
        $this->get(route('employee.work-calendar', ['month' => '2026-12']))->assertOk()
            ->assertSee('data-leave-segment="'.$leave->id.'"', false);
        $this->get(route('employee.work-calendar', ['day' => '2026-11-30', 'details' => 1]))->assertOk()
            ->assertSeeText(['Assigned office hours', 'Calendar regular', 'Calendar special_non_working', 'Calendar special_working', 'Calendar company', 'Time In 8:25 AM', 'Approved leave'])
            ->assertSee(route('employee.leave.show', $leave), false)
            ->assertSee(route('attendance.show', $record), false)
            ->assertDontSee(route('attendance.show', $otherRecord), false)
            ->assertDontSee(route('employee.leave.show', $privateLeave), false);
        $this->assertDatabaseCount('leave_requests', 2);
        $this->assertDatabaseCount('attendance_records', 2);
        $this->assertDatabaseCount('attendance_holidays', 4);
    }

    public function test_an_approved_leave_day_with_a_holiday_remains_authorized(): void
    {
        $this->travelTo($this->manila('07:00'));
        $employee = $this->attendee();
        WorkSchedule::factory()->create();
        $holiday = AttendanceHoliday::factory()->create(['holiday_date' => '2026-11-19']);
        $leave = LeaveRequest::factory()->create(['employee_id' => $employee->id, 'start_date' => '2026-11-19', 'end_date' => '2026-11-19', 'status' => 'approved']);

        $policy = app(AttendancePolicyResolver::class)->resolve($employee, '2026-11-19', $employee->currentAssignment->branch);

        $this->assertSame('approved_leave', $policy['obligation']);
        $this->assertSame([$leave->id], $policy['leave_ids']);
        $this->assertSame([$holiday->id], array_column($policy['holidays'], 'id'));
    }

    public function test_csv_confirmation_imports_only_selected_verified_rows_and_rejects_replay(): void
    {
        $this->actingAs($this->reviewer());
        $data = [
            'year' => 2026, 'source_url' => 'https://officialgazette.gov.ph/fixture', 'declaration' => 'Sample proclamation reference',
            'csv' => UploadedFile::fake()->createWithContent('holidays.csv', "date,name,type,work_expected\n2026-11-19,Sample holiday,regular,0\n2026-11-20,Sample working day,special_working,1\n"),
        ];
        $this->post(route('hr.holiday-import.preview'), $data)->assertSessionHasErrors('verified');
        $preview = $this->post(route('hr.holiday-import.preview'), [...$data, 'verified' => 1])
            ->assertOk()
            ->assertSeeText(['Sample working day', 'Select all']);
        $payload = ['token' => $preview->viewData('token'), 'selected' => [0], 'confirm' => 1];

        $this->post(route('hr.holiday-import.store'), $payload)->assertRedirectToRoute('hr.holiday-import.index');

        $this->assertDatabaseCount('attendance_holidays', 1);
        $this->assertSame('Sample holiday', AttendanceHoliday::sole()->name);
        $this->assertSame(1, HolidayImport::sole()->imported_count);
        $this->post(route('hr.holiday-import.store'), $payload)->assertSessionHasErrors('csv');
        $this->get(route('hr.holiday-import.index'))->assertOk()->assertSeeText('Sample proclamation reference');
    }

    public function test_import_warns_of_conflicts_and_preserves_manual_declarations(): void
    {
        $manual = AttendanceHoliday::factory()->create(['holiday_date' => '2026-11-19', 'type' => 'company']);
        $this->actingAs($this->reviewer());
        $preview = $this->post(route('hr.holiday-import.preview'), [
            'year' => 2026, 'source_url' => 'https://www.gov.ph/fixture', 'declaration' => 'Sample proclamation', 'verified' => 1,
            'csv' => UploadedFile::fake()->createWithContent('holidays.csv', "date,name,type,work_expected\n2026-11-19,Working declaration,special_working,1\n"),
        ])->assertOk()->assertSeeText('Overlapping classification');
        $payload = ['token' => $preview->viewData('token'), 'selected' => [0], 'confirm' => 1];
        $this->post(route('hr.holiday-import.store'), $payload)->assertSessionHasErrors('allow_conflicts');

        $this->post(route('hr.holiday-import.store'), [...$payload, 'allow_conflicts' => 1])->assertRedirect();

        $this->assertSame('company', $manual->fresh()->type);
        $this->assertDatabaseCount('attendance_holidays', 2);
    }

    public function test_after_midnight_time_in_uses_the_previous_overnight_shift(): void
    {
        $date = CarbonImmutable::parse('2026-11-20 00:15', 'Asia/Manila');
        $this->travelTo($date);
        $employee = $this->attendee();
        WorkSchedule::factory()->create(['starts_at' => '23:00', 'ends_at' => '07:00', 'break_starts_at' => '02:00', 'break_ends_at' => '03:00']);
        AttendanceHoliday::factory()->create(['holiday_date' => '2026-11-20', 'type' => 'company']);
        $this->punch($this->capture($employee))->assertOk();
        $this->travelTo($date->setTime(6, 40));
        $this->punch($this->capture($employee, 'time_out'), color: 180)->assertOk();

        $record = AttendanceRecord::sole();
        $this->assertSame('2026-11-19', $record->work_date->toDateString());
        $this->assertSame([], $record->computation->holiday_types);
        $this->assertSame(75, $record->computation->late_minutes);
        $this->assertSame(20, $record->computation->undertime_minutes);
        $this->assertSame(325, $record->computation->net_minutes);
    }

    public function test_hr_can_configure_an_overnight_schedule_and_break(): void
    {
        $this->travelTo($this->manila('07:00'));
        $this->actingAs($this->reviewer());
        $input = $this->scheduleInput(['starts_at' => '23:00', 'ends_at' => '07:00', 'break_starts_at' => '02:00', 'break_ends_at' => '03:00']);

        $this->post(route('hr.schedules.store'), $input)->assertRedirectToRoute('hr.schedules.index')->assertSessionHasNoErrors();
        $this->post(route('hr.schedules.store'), [...$input, 'ends_at' => '23:00'])->assertSessionHasErrors('ends_at');

        $this->assertSame('07:00', substr(WorkSchedule::sole()->ends_at, 0, 5));
    }

    public function test_hr_can_assign_one_policy_to_multiple_branches_atomically(): void
    {
        $this->actingAs($this->reviewer());
        $branches = Branch::factory()->count(3)->create();
        $input = $this->scheduleInput(['scope' => 'branch', 'branch_ids' => $branches->take(2)->modelKeys()]);

        $this->post(route('hr.schedules.store'), $input)->assertRedirectToRoute('hr.schedules.index')->assertSessionHasNoErrors();
        $this->post(route('hr.schedules.store'), [...$input, 'branch_ids' => [$branches[2]->id, $branches[0]->id]])
            ->assertSessionHasErrors('effective_from');

        $this->assertSame($branches->take(2)->modelKeys(), WorkSchedule::orderBy('id')->pluck('branch_id')->all());
    }

    public function test_working_holiday_on_a_rest_day_and_cancelled_holidays_create_no_work_obligation(): void
    {
        $this->travelTo($this->manila('07:00'));
        $employee = $this->attendee();
        WorkSchedule::factory()->create();
        AttendanceHoliday::factory()->create(['holiday_date' => '2026-11-21', 'type' => 'special_working', 'work_expected' => true]);
        AttendanceHoliday::factory()->create(['holiday_date' => '2026-11-20', 'type' => 'regular', 'status' => 'cancelled']);
        $resolver = app(AttendancePolicyResolver::class);

        $restDay = $resolver->resolve($employee, '2026-11-21', $employee->currentAssignment->branch);
        $workDay = $resolver->resolve($employee, '2026-11-20', $employee->currentAssignment->branch);

        $this->assertSame('not_scheduled', $restDay['obligation']);
        $this->assertSame(['special_working'], array_column($restDay['holidays'], 'type'));
        $this->assertSame('work', $workDay['obligation']);
        $this->assertSame([], $workDay['holidays']);
    }

    public function test_hr_can_approve_an_early_departure_without_changing_punch_times(): void
    {
        $this->travelTo($this->manila('07:00'));
        $employee = $this->attendee();
        WorkSchedule::factory()->create();
        $this->actingAs($this->reviewer())->post(route('hr.schedule-exceptions.store'), [
            'employee_id' => $employee->id, 'work_date' => '2026-11-19', 'kind' => 'early_departure',
            'adjusted_end' => '11:00', 'reason' => 'Approved early departure for an appointment.',
        ])->assertRedirectToRoute('hr.schedules.index')->assertSessionHasNoErrors();
        $this->travelTo($this->manila('08:00'));
        $this->punch($this->capture($employee))->assertOk();
        $this->travelTo($this->manila('11:00'));
        $this->punch($this->capture($employee, 'time_out'), color: 180)->assertOk();

        $record = AttendanceRecord::sole();
        $this->assertSame(180, $record->computation->expected_minutes);
        $this->assertSame(180, $record->computation->net_minutes);
        $this->assertSame(0, $record->computation->undertime_minutes);
        $this->assertNull($record->effective_time_out_at);
        $this->assertSame('early_departure', $record->computation->policy['exception']['kind']);
    }

    public function test_only_an_approved_correction_updates_effective_working_hours(): void
    {
        $this->travelTo($this->manila('08:25'));
        $employee = $this->attendee();
        WorkSchedule::factory()->create();
        $this->punch($this->capture($employee))->assertOk();
        $record = AttendanceRecord::sole();
        $original = $record->computation;
        $this->travelTo($this->manila('16:00'));
        $this->post(route('employee.corrections.store'), [
            'attendance_record_id' => $record->id, 'correction_type' => 'forgotten_time_out', 'requested_date' => '2026-11-19',
            'requested_time_out_at' => '2026-11-19T15:30', 'reason' => 'Forgot to record the authorized checkout.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($original->id, $record->fresh()->computation->id);
        $correction = AttendanceCorrectionRequest::sole();

        $this->actingAs($this->reviewer())->post(route('hr.corrections.decide', $correction), [
            'decision' => 'approved', 'remarks' => 'Confirmed the effective Time Out with independent evidence.', 'confirmation' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $record->refresh();
        $this->assertNull($record->time_out_at);
        $this->assertSame(365, $record->computation->net_minutes);
        $this->assertSame(30, $record->computation->undertime_minutes);
        $this->assertTrue($original->fresh()->incomplete);
        $this->assertDatabaseCount('attendance_events', 1);
    }

    public function test_holiday_exclusion_caps_chargeable_leave_days_at_submission_and_flags_later_changes(): void
    {
        $this->travelTo($this->manila('07:00'));
        $employee = $this->attendee();
        $supervisor = User::factory()->create(['role_id' => Role::SUPERVISOR, 'supervisor_department_id' => $employee->department_id]);
        SupervisorAssignment::factory()->create(['employee_id' => $employee->id, 'supervisor_user_id' => $supervisor->id]);
        $type = LeaveType::factory()->create(['affects_balance' => true, 'duration_policy' => 'exclude_nonworking_holidays']);
        $holiday = AttendanceHoliday::factory()->create(['holiday_date' => '2026-11-20', 'type' => 'regular']);
        $this->actingAs($employee->user)->post(route('employee.leave.store'), [
            'start_date' => '2026-11-19', 'end_date' => '2026-11-20', 'leave_type_id' => $type->id,
            'reason' => 'Attending to a personal appointment.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $application = LeaveRequest::sole();
        $this->assertSame(['2026-11-20'], $application->form_snapshot['attendance_policy']['excluded_dates']);
        $this->assertSame(1, $application->form_snapshot['attendance_policy']['maximum_charge']);

        $holiday->update(['status' => 'cancelled']);
        $this->get(route('employee.leave.show', $application))->assertOk()->assertSeeText(['Review needed', '2026-11-20']);
        $this->actingAs($supervisor)->postJson(route('supervisor.leave.decide', $application), [
            'decision' => 'approved', 'current_password' => 'password', 'signature_version' => 1,
            'confirmation' => 1, 'chargeable_days' => 2,
        ])->assertUnprocessable()->assertJsonValidationErrors('chargeable_days');

        $this->assertSame($application->form_snapshot, $application->fresh()->form_snapshot);
        $this->assertDatabaseCount('leave_balance_transactions', 0);
    }

    public function test_supervisors_and_employees_cannot_manage_attendance_policies(): void
    {
        $supervisor = User::factory()->create(['role_id' => Role::SUPERVISOR]);
        $this->actingAs($supervisor)->get(route('hr.holidays.index'))->assertForbidden();
        $this->get(route('employee.work-calendar'))->assertForbidden();
        $this->actingAs($this->attendee()->user)->post(route('hr.holidays.store'), $this->holidayInput())->assertForbidden();
        $this->post(route('hr.schedules.store'), $this->scheduleInput())->assertForbidden();
        $this->assertDatabaseCount('attendance_holidays', 0);
    }
}

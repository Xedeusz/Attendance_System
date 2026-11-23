<?php

namespace Tests\Feature;

use App\Models\AccountInvitation;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceHoliday;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\AttendanceComputationService;
use App\Services\AttendancePolicyResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith([null])]
    #[TestWith([Role::EMPLOYEE])]
    #[TestWith([Role::SUPERVISOR])]
    public function test_report_endpoints_require_hr(?int $role): void
    {
        $employee = Employee::factory()->create();
        if ($role !== null) {
            $this->actingAs(User::factory()->create(['role_id' => $role]));
        }
        foreach (['index' => [], 'export' => [], 'show' => ['employee' => $employee, 'date' => '2026-11-19']] as $action => $parameters) {
            $response = $this->get(route('hr.attendance-reports.'.$action, $parameters));
            $role === null ? $response->assertRedirectToRoute('login') : $response->assertForbidden();
        }
    }

    public function test_filters_pagination_csv_and_details_share_the_same_report(): void
    {
        $this->travelTo($this->local('2026-11-25 09:00'));
        $hr = User::factory()->create(['role_id' => Role::HR]);
        $branch = Branch::factory()->create(['branch_name' => '+Main, "HQ"']);
        $employee = $this->employee($branch, ['first_name' => '=Élodie', 'employee_number' => '@E-001']);
        $otherBranch = Branch::factory()->create();
        $other = $this->employee($otherBranch);
        $sameBranch = $this->employee($branch);
        WorkSchedule::factory()->create(['created_by' => $hr->id]);
        $this->punch($employee, $branch, '2026-11-02', '08:15', '15:30');
        $this->punch($employee, $branch, '2026-11-03', '08:00', '16:00');
        $this->punch($employee, $branch, '2026-11-04', '08:00', null);
        $this->punch($employee, $branch, '2026-11-09', '08:20', '16:00');
        $this->punch($employee, $branch, '2026-11-10', '08:00', '15:30');

        $filters = ['from' => '2026-11-01', 'to' => '2026-11-30', 'branch_id' => $branch->id,
            'department_id' => $employee->department_id, 'employee_id' => $employee->id];
        $this->actingAs($hr);
        $report = $this->get(route('hr.attendance-reports.index', $filters))->assertOk();
        $summary = $report->viewData('summary');
        $this->assertSame(21, $summary['Scheduled Workdays']);
        $this->assertSame(5, $summary['Present Days']);
        $this->assertSame(2, $summary['Late Arrivals']);
        $this->assertSame(1585, $summary['Total Work Hours']);
        $this->assertSame(12, $summary['Absences']);
        $this->assertSame(0, $summary['Approved Leave Days']);
        $this->assertSame(25, $report->viewData('rows')->count());
        $this->assertSame(30, $report->viewData('rows')->total());
        $this->assertSame([$employee->id], $report->viewData('employees')->modelKeys());

        $pageTwo = $this->get(route('hr.attendance-reports.index', [...$filters, 'page' => 2]))->assertOk();
        $this->assertSame($summary, $pageTwo->viewData('summary'));
        $this->assertSame('2026-11-26', $pageTwo->viewData('rows')->first()['date']);

        $response = $this->get(route('hr.attendance-reports.export', [...$filters, 'page' => 2]))
            ->assertDownload('LIMCOMA_Attendance_Report_2026-11-01_to_2026-11-30.csv')
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        [$headers, $rows] = $this->parseCsv($response->streamedContent());
        $this->assertSame(['Date', 'Employee ID', 'Employee Name', 'Branch', 'Department', 'Schedule', 'Time In', 'Time Out',
            'Work Hours (Minutes)', 'Late (Minutes)', 'Undertime (Minutes)', 'Attendance Status', 'Leave Status'], $headers);
        $this->assertCount(30, $rows);
        $this->assertSame(12, collect($rows)->where('Attendance Status', 'Absent')->count());
        $this->assertSame($summary['Total Work Hours'], collect($rows)->sum(fn (array $row): int => (int) $row['Work Hours (Minutes)']));
        $this->assertSame("'@E-001", $rows[1]['Employee ID']);
        $this->assertSame("'=Élodie Employee", $rows[1]['Employee Name']);
        $this->assertSame("'+Main, \"HQ\"", $rows[1]['Branch']);
        $this->assertSame(['375', '15', '30'], array_slice(array_values($rows[1]), 8, 3));
        $this->assertSame('Present; Incomplete attendance', $rows[3]['Attendance Status']);
        $this->assertSame('', $rows[3]['Work Hours (Minutes)']);

        $this->get(route('hr.attendance-reports.show', [...$filters, 'employee' => $employee, 'date' => '2026-11-02', 'page' => 2]))
            ->assertOk()->assertSeeText(['6h 15m', '15m', '30m', 'Back to Attendance Reports']);
        $this->get(route('hr.attendance-reports.show', [...$filters, 'employee' => $other, 'date' => '2026-11-02']))->assertNotFound();
        $this->get(route('hr.attendance-reports.index', ['from' => '2026-11-02', 'to' => '2026-11-02', 'branch_id' => $branch->id]))
            ->assertOk()->assertViewHas('rows', fn ($rows) => array_column($rows->items(), 'employee_id') === [$employee->id, $sameBranch->id]);
        $this->get(route('hr.attendance-reports.index', ['from' => '2026-11-02', 'to' => '2026-11-02', 'department_id' => $other->department_id]))
            ->assertOk()->assertViewHas('rows', fn ($rows) => array_column($rows->items(), 'employee_id') === [$other->id]);
        $this->get(route('hr.attendance-reports.index', ['from' => '2026-11-02', 'to' => '2026-11-02', 'employee_id' => $sameBranch->id]))
            ->assertOk()->assertViewHas('rows', fn ($rows) => array_column($rows->items(), 'employee_id') === [$sameBranch->id]);
        $this->get(route('hr.attendance-reports.index', [...$filters, 'direction' => 'desc']))
            ->assertOk()->assertViewHas('rows', fn ($rows) => $rows->first()['date'] === '2026-11-30');
    }

    public function test_report_resolves_leave_holidays_corrections_and_overnight_shifts(): void
    {
        $this->travelTo($this->local('2026-11-20 09:00'));
        $branch = Branch::factory()->create();
        $employee = $this->employee($branch);
        WorkSchedule::factory()->create(['effective_until' => '2026-11-18']);
        WorkSchedule::factory()->create(['name' => 'Night shift', 'effective_from' => '2026-11-19', 'starts_at' => '22:00',
            'ends_at' => '06:00', 'break_starts_at' => '02:00', 'break_ends_at' => '03:00']);
        AttendanceHoliday::factory()->create(['holiday_date' => '2026-11-16', 'work_expected' => false]);
        LeaveRequest::factory()->for($employee)->create(['status' => 'approved', 'finalized_at' => now(),
            'start_date' => '2026-11-13', 'end_date' => '2026-11-17']);
        LeaveRequest::factory()->for($employee)->create(['status' => 'approved', 'finalized_at' => now(),
            'start_date' => '2026-11-18', 'end_date' => '2026-11-18', 'half_day' => 'am', 'requested_duration' => 0.5]);
        LeaveRequest::factory()->for($employee)->create(['status' => 'pending_supervisor_approval',
            'start_date' => '2026-11-19', 'end_date' => '2026-11-19']);
        LeaveRequest::factory()->for($employee)->create(['status' => 'rejected',
            'start_date' => '2026-11-20', 'end_date' => '2026-11-20']);

        $record = $this->punch($employee, $branch, '2026-11-18', '13:00', null);
        $record->forceFill(['effective_time_out_at' => $this->local('2026-11-18 16:00')->utc(), 'correction_version' => 1])->save();
        app(AttendanceComputationService::class)->capture($record->refresh(), 'correction');
        $manual = AttendanceCorrectionRequest::factory()->for($employee)->create([
            'branch_id' => $branch->id, 'status' => 'approved', 'requested_date' => '2026-11-19',
            'requested_time_in_at' => $this->local('2026-11-19 22:00')->utc(),
            'requested_time_out_at' => $this->local('2026-11-20 06:00')->utc(),
        ]);
        app(AttendanceComputationService::class)->captureManual($manual);
        AttendanceCorrectionRequest::factory()->for($employee)->create(['requested_date' => '2026-11-20']);

        $filters = ['from' => '2026-11-15', 'to' => '2026-11-23', 'employee_id' => $employee->id];
        $this->actingAs(User::factory()->create(['role_id' => Role::HR]));
        $report = $this->get(route('hr.attendance-reports.index', $filters))->assertOk();
        $days = collect($report->viewData('rows')->items())->keyBy('date');
        $this->assertSame(1.5, $report->viewData('summary')['Approved Leave Days']);
        $this->assertSame(600, $report->viewData('summary')['Total Work Hours']);
        $this->assertSame(0, $report->viewData('summary')['Absences']);
        $this->assertSame('Rest day', $days['2026-11-15']['status']);
        $this->assertSame('Holiday', $days['2026-11-16']['status']);
        $this->assertSame('Approved leave', $days['2026-11-17']['leave']);
        $this->assertSame('Approved leave (AM)', $days['2026-11-18']['leave']);
        $this->assertNull($days['2026-11-18']['late']);
        $this->assertStringContainsString('Approved correction', $days['2026-11-18']['status']);
        $this->assertSame('2026-11-20 06:00:00', $days['2026-11-19']['out']);
        $this->assertSame('Pending leave', $days['2026-11-19']['leave']);
        $this->assertSame('Pending correction', $days['2026-11-20']['status']);
        $this->assertSame('', $days['2026-11-20']['leave']);
        $this->assertFalse($days['2026-11-23']['absent']);

        [, $csv] = $this->parseCsv($this->get(route('hr.attendance-reports.export', $filters))->streamedContent());
        $this->assertSame('Approved leave', $csv[2]['Leave Status']);
        $this->assertSame('420', $csv[4]['Work Hours (Minutes)']);
    }

    public function test_historical_branch_and_activation_limit_synthetic_days(): void
    {
        $this->travelTo($this->local('2026-11-25 18:00'));
        $first = Branch::factory()->create();
        $second = Branch::factory()->create();
        $employee = $this->employee($first);
        $employee->assignments()->update(['effective_until' => $this->local('2026-11-24 00:00')->utc()]);
        $employee->assignments()->create(['branch_id' => $second->id, 'is_primary' => true,
            'effective_from' => $this->local('2026-11-24 00:00')->utc()]);
        AccountInvitation::create(['user_id' => $employee->user_id, 'token_hash' => hash('sha256', 'fixture'),
            'expires_at' => now(), 'used_at' => $this->local('2026-11-23 08:00')->utc()]);
        $this->employee($first, ['date_hired' => '2026-11-27']);
        WorkSchedule::factory()->create();
        $this->actingAs(User::factory()->create(['role_id' => Role::HR]));
        $this->get(route('hr.attendance-reports.index', ['from' => '2026-11-20', 'to' => '2026-11-25', 'branch_id' => $first->id]))
            ->assertOk()->assertViewHas('rows', fn ($rows) => array_column($rows->items(), 'date') === ['2026-11-23']);
    }

    #[TestWith(['from', '2026-02-31'])]
    #[TestWith(['to', '2026-10-31'])]
    #[TestWith(['to', '2027-03-01'])]
    #[TestWith(['branch_id', 999])]
    #[TestWith(['department_id', 999])]
    #[TestWith(['employee_id', 999])]
    public function test_report_and_export_validate_filters(string $field, string|int $value): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::HR]));
        $filters = ['from' => '2026-11-01', 'to' => '2026-11-30', $field => $value];
        foreach (['index', 'export'] as $action) {
            $this->get(route('hr.attendance-reports.'.$action, $filters))->assertSessionHasErrors($field);
        }
    }

    private function employee(Branch $branch, array $attributes = []): Employee
    {
        $employee = Employee::factory()->create($attributes);
        $employee->assignments()->create(['branch_id' => $branch->id, 'is_primary' => true,
            'effective_from' => $this->local('2026-01-01 00:00')->utc()]);

        return $employee;
    }

    private function punch(Employee $employee, Branch $branch, string $date, string $in, ?string $out): AttendanceRecord
    {
        $record = AttendanceRecord::create([
            'employee_id' => $employee->id, 'branch_id' => $branch->id, 'work_date' => $date,
            'time_in_at' => $this->local("$date $in")->utc(), 'time_out_at' => $out ? $this->local("$date $out")->utc() : null,
            'session_status' => $out ? 'completed' : 'open', 'verification_status' => 'location_validated', 'review_status' => 'not_required',
        ]);
        app(AttendanceComputationService::class)->capture($record, initialPolicy: app(AttendancePolicyResolver::class)->resolve($employee, $date, $branch));

        return $record;
    }

    private function local(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, config('attendance.timezone'));
    }

    private function parseCsv(string $csv): array
    {
        $stream = fopen('php://memory', 'w+b');
        fwrite($stream, $csv);
        rewind($stream);
        $headers = fgetcsv($stream, escape: '');
        $rows = [];
        while (($cells = fgetcsv($stream, escape: '')) !== false) {
            $rows[] = array_combine($headers, $cells);
        }
        fclose($stream);

        return [$headers, $rows];
    }
}

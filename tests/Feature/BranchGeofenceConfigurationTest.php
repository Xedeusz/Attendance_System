<?php

namespace Tests\Feature;

use App\Models\AttendanceEvent;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AttendanceData;
use Tests\Support\Phase3Data;
use Tests\TestCase;

class BranchGeofenceConfigurationTest extends TestCase
{
    use AttendanceData, Phase3Data, RefreshDatabase;

    public function test_hr_saves_selected_geofence_and_records_configuration_audit(): void
    {
        $actor = $this->hr();
        $data = $this->branchData();

        $this->actingAs($actor)->post(route('hr.branches.store'), $data)->assertSessionHasNoErrors()->assertRedirect();

        $branch = Branch::sole();
        $this->assertDatabaseHas('branches', $data);
        $audit = AuditLog::where('action', 'branch.created')->sole();
        $this->assertSame($actor->id, $audit->actor_user_id);
        $this->assertSame($branch->id, $audit->subject_id);
        $this->assertNull($audit->metadata['geofence']['previous']);
        $this->assertSame(150, $audit->metadata['geofence']['current']['geofence_radius_meters']);
        $this->get(route('hr.branches.edit', $branch))->assertOk()->assertSee('branchLocationMap')->assertSee('14.5995');
        $this->get(route('hr.branches.show', $branch))->assertOk()->assertSee('14.5995')->assertDontSee('Reset Location');
    }

    public function test_active_branch_requires_explicit_location_and_radius(): void
    {
        $this->actingAs($this->hr())->post(route('hr.branches.store'), $this->branchData([
            'latitude' => null, 'longitude' => null, 'geofence_radius_meters' => null,
        ]))->assertSessionHasErrors([
            'latitude' => 'Please select the branch location on the map.',
            'longitude' => 'Please select the branch location on the map.',
            'geofence_radius_meters' => 'Enter a valid geofence radius in meters.',
        ]);

        $this->assertDatabaseCount('branches', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_inactive_branch_can_remain_unconfigured_and_legacy_branches_render_safely(): void
    {
        $data = $this->branchData(['status' => 'inactive', 'latitude' => null, 'longitude' => null, 'geofence_radius_meters' => null]);

        $this->actingAs($this->hr())->post(route('hr.branches.store'), $data)->assertSessionHasNoErrors()->assertRedirect();

        $branch = Branch::sole();
        $this->assertDatabaseHas('branches', $data);
        $this->get(route('hr.branches.edit', $branch))->assertOk()->assertSee('Location is not configured for this branch.');
        $this->get(route('hr.branches.show', $branch))->assertOk()->assertSee('Not configured');
        $branch->update(['status' => 'active']);
        $this->get(route('hr.branches.edit', $branch))->assertOk()->assertSee('Location is not configured for this branch.');
    }

    #[DataProvider('invalidGeofences')]
    public function test_invalid_geofence_is_rejected_without_writes(array $overrides, string $field): void
    {
        config(['attendance.geofence_min_radius_meters' => 25, 'attendance.geofence_max_radius_meters' => 500]);
        $branch = Branch::factory()->create($this->branchData());
        $before = $branch->fresh()->getAttributes();

        $this->actingAs($this->hr())->put(route('hr.branches.update', $branch), [...$branch->toArray(), ...$overrides])->assertSessionHasErrors($field);

        $this->assertSame($before, $branch->fresh()->getAttributes());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public static function invalidGeofences(): array
    {
        return [
            'empty radius' => [['geofence_radius_meters' => null], 'geofence_radius_meters'],
            'zero radius' => [['geofence_radius_meters' => 0], 'geofence_radius_meters'],
            'negative radius' => [['geofence_radius_meters' => -1], 'geofence_radius_meters'],
            'nonnumeric radius' => [['geofence_radius_meters' => 'invalid'], 'geofence_radius_meters'],
            'fractional radius' => [['geofence_radius_meters' => 50.5], 'geofence_radius_meters'],
            'scientific radius' => [['geofence_radius_meters' => '1e2'], 'geofence_radius_meters'],
            'decimal notation radius' => [['geofence_radius_meters' => '100.0'], 'geofence_radius_meters'],
            'below configured policy' => [['geofence_radius_meters' => 24], 'geofence_radius_meters'],
            'above configured policy' => [['geofence_radius_meters' => 501], 'geofence_radius_meters'],
            'latitude outside earth' => [['latitude' => 91], 'latitude'],
            'longitude outside earth' => [['longitude' => -181], 'longitude'],
            'nonnumeric coordinate' => [['latitude' => 'invalid'], 'latitude'],
            'partial inactive location' => [['status' => 'inactive', 'longitude' => null], 'longitude'],
            'inactive radius without center' => [['status' => 'inactive', 'latitude' => null, 'longitude' => null], 'latitude'],
        ];
    }

    public function test_validation_redirect_preserves_unsaved_map_values(): void
    {
        $branch = Branch::factory()->create($this->branchData());
        $editUrl = route('hr.branches.edit', $branch);
        $this->actingAs($this->hr())->from($editUrl)->put(route('hr.branches.update', $branch), [
            ...$branch->toArray(), 'branch_name' => '', 'latitude' => 13.9411, 'longitude' => 121.1622, 'geofence_radius_meters' => 200,
        ])->assertRedirect($editUrl)->assertSessionHasErrors('branch_name')->assertSessionHasInput('latitude', 13.9411);

        $this->get($editUrl)->assertOk()->assertSee('13.9411')->assertSee('121.1622');
        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'latitude' => 14.5995, 'geofence_radius_meters' => 150]);
    }

    public function test_guests_and_employees_cannot_access_or_submit_branch_configuration(): void
    {
        $branch = Branch::factory()->create($this->branchData());
        $this->get(route('hr.branches.create'))->assertRedirect(route('login'));
        $this->put(route('hr.branches.update', $branch), $this->branchData())->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        $this->get(route('hr.branches.create'))->assertForbidden();
        $this->get(route('hr.branches.edit', $branch))->assertForbidden();
        $this->get(route('hr.branches.show', $branch))->assertForbidden();
        $this->post(route('hr.branches.store'), $this->branchData())->assertForbidden();
        $this->put(route('hr.branches.update', $branch), [...$branch->toArray(), 'geofence_radius_meters' => 500])->assertForbidden();

        $this->assertDatabaseCount('branches', 1);
        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'geofence_radius_meters' => 150]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_saved_changes_apply_to_future_attendance_and_preserve_history_and_audits(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $originalEvent = AttendanceEvent::sole();
        $originalSnapshot = $originalEvent->branch_snapshot;
        $staleCapture = $this->capture($employee, 'time_out');
        $branch = $employee->currentAssignment->branch;
        $actor = $this->hr();

        $this->actingAs($actor)->put(route('hr.branches.update', $branch), [
            ...$branch->toArray(), 'latitude' => 14.6095, 'longitude' => 120.9942, 'geofence_radius_meters' => 50,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $audit = AuditLog::where('action', 'branch.updated')->sole();
        $this->assertSame($actor->id, $audit->actor_user_id);
        $this->assertSame(150, $audit->metadata['geofence']['previous']['geofence_radius_meters']);
        $this->assertSame(50, $audit->metadata['geofence']['current']['geofence_radius_meters']);
        $this->assertSame(14.5995, (float) $audit->metadata['geofence']['previous']['latitude']);
        $this->assertSame(14.6095, (float) $audit->metadata['geofence']['current']['latitude']);

        $this->actingAs($employee->user);
        $this->punch($staleCapture, [], 180)->assertConflict();
        $capture = $this->capture($employee->fresh(), 'time_out');
        $this->assertSame(50, $capture['branch']['radius']);
        $this->postJson('/employee/attendance/captures/'.$capture['id'].'/location', ['token' => $capture['token'], ...$this->position()])->assertOk()->assertJsonPath('status', 'outside_geofence');
        $this->punch($capture, $this->position(['latitude' => '14.6095', 'longitude' => '120.9942']), 180)->assertOk();

        $this->assertSame($originalSnapshot, $originalEvent->fresh()->branch_snapshot);
        $this->assertSame('within_geofence', $originalEvent->fresh()->geofence_status);
        $this->assertSame(50, AttendanceEvent::where('event_type', 'time_out')->sole()->branch_snapshot['radius']);
        $this->assertDatabaseCount('attendance_events', 2);
    }

    private function branchData(array $overrides = []): array
    {
        return array_replace(Branch::factory()->make()->toArray(), [
            'latitude' => 14.5995, 'longitude' => 120.9842, 'geofence_radius_meters' => 150,
        ], $overrides);
    }
}

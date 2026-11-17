<?php

namespace Tests\Feature;

use App\Events\WorkspaceChanged;
use App\Jobs\BroadcastWorkspaceChange;
use App\Models\Branch;
use App\Models\LeaveRequest;
use App\Models\LeaveSupportingDocument;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Services\EditVersion;
use App\Services\WorkflowNotices;
use App\Services\WorkspaceSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class WorkspaceSyncTest extends TestCase
{
    use AttendanceData, RefreshDatabase;

    public function test_state_requires_authentication_and_excludes_other_employees_revisions(): void
    {
        $this->getJson('/realtime/state')->assertUnauthorized();
        $owner = $this->attendee();
        $other = $this->attendee();
        app(WorkspaceSync::class)->touch($owner->user, ['attendance']);

        $this->actingAs($owner->user)->getJson('/realtime/state?attendance=1')->assertOk()
            ->assertJsonStructure(['revisions' => ['attendance'], 'attendance' => ['canTimeIn', 'canTimeOut']]);
        $this->actingAs($other->user)->getJson('/realtime/state')->assertOk()->assertJsonMissingPath('revisions.attendance');
    }

    public function test_private_channels_reject_other_users_roles_and_revoked_session_versions(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test']);
        require base_path('routes/channels.php');
        $employee = $this->attendee();
        $other = $this->attendee();
        $this->actingAs($employee->user);
        $this->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-'.WorkspaceSync::channel($employee->user)])->assertOk();
        foreach ([WorkspaceSync::channel($other->user), 'workspace.'.$employee->user_id.'.1.1', 'workspace.'.$employee->user_id.'.0.2', 'hr.attendance'] as $channel) {
            $this->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-'.$channel])->assertForbidden();
        }
    }

    public function test_supervisor_state_and_channel_do_not_expose_attendance_or_corrections(): void
    {
        $supervisor = User::factory()->create(['role_id' => Role::SUPERVISOR]);
        app(WorkspaceSync::class)->touch($supervisor, ['leave', 'attendance', 'corrections']);

        $this->actingAs($supervisor)->getJson('/realtime/state?attendance=1')->assertOk()
            ->assertJsonStructure(['revisions' => ['leave']])->assertJsonMissingPath('attendance')
            ->assertJsonMissingPath('revisions.attendance')->assertJsonMissingPath('revisions.corrections');
        $this->getJson('/hr/attendance')->assertForbidden();
    }

    public function test_attendance_commit_updates_hr_and_owner_without_notifying_another_employee(): void
    {
        $hr = $this->reviewer();
        $employee = $this->attendee();
        $other = $this->attendee();
        Queue::fake([BroadcastWorkspaceChange::class]);

        $eventId = $this->punch($this->capture($employee))->assertOk()->json('event_id');
        $this->getJson('/realtime/state?attendance=1')->assertOk()->assertJsonPath('attendance.latestResult.event_id', $eventId);
        $this->actingAs($other->user)->getJson('/realtime/state?attendance=1')->assertOk()->assertJsonPath('attendance.latestResult', null);

        $this->assertDatabaseHas('workspace_revisions', ['user_id' => $hr->id, 'topic' => 'attendance']);
        $this->assertDatabaseHas('workspace_revisions', ['user_id' => $employee->user_id, 'topic' => 'attendance']);
        $this->assertDatabaseMissing('workspace_revisions', ['user_id' => $other->user_id, 'topic' => 'attendance']);
        Queue::assertPushed(BroadcastWorkspaceChange::class, fn ($job) => $job->userId === $hr->id);
    }

    public function test_rolled_back_changes_do_not_leave_revisions_or_queue_success_signals(): void
    {
        $hr = $this->reviewer();
        $branch = Branch::factory()->create();
        Queue::fake([BroadcastWorkspaceChange::class]);
        DB::beginTransaction();
        app(AuditService::class)->record('branch.updated', $branch);
        DB::rollBack();

        $this->assertDatabaseMissing('workspace_revisions', ['user_id' => $hr->id, 'topic' => 'branches']);
        Queue::assertNotPushed(BroadcastWorkspaceChange::class);
    }

    public function test_queued_job_discards_a_revoked_recipient_and_payload_contains_only_revisions(): void
    {
        $employee = $this->attendee();
        $job = new BroadcastWorkspaceChange($employee->user_id, $employee->user->session_version, $employee->user->role_id, ['attendance' => 'revision']);
        $employee->user->forceFill(['session_version' => $employee->user->session_version + 1])->save();
        Event::fake([WorkspaceChanged::class]);

        $job->handle();

        Event::assertNotDispatched(WorkspaceChanged::class);
        $event = new WorkspaceChanged('private-user', ['attendance' => 'revision']);
        $this->assertSame(['revisions' => ['attendance' => 'revision']], $event->broadcastWith());
    }

    public function test_notification_creation_and_read_refresh_the_owners_count_only(): void
    {
        $employee = $this->attendee();
        app(WorkflowNotices::class)->send($employee->user, 'leave', 'Synthetic notice', '/employee/leave', 1);
        $this->actingAs($employee->user);
        $before = $this->getJson('/realtime/state')->assertOk()->assertJsonPath('unread', 1)->json('revisions.notifications');
        $notice = $employee->user->notifications()->sole();

        $this->post('/notifications/'.$notice->id.'/read')->assertRedirect();

        $after = $this->getJson('/realtime/state')->assertOk()->assertJsonPath('unread', 0)->json('revisions.notifications');
        $this->assertNotSame($before, $after);
    }

    public function test_fragments_use_authorized_filtered_views_without_the_page_shell(): void
    {
        $this->actingAs($this->reviewer());
        Branch::factory()->create(['branch_name' => 'Matching Office']);
        Branch::factory()->create(['branch_name' => 'Other Office']);

        $this->withHeader('X-Workspace-Sync', '1')->get('/hr/branches?q=Matching')
            ->assertOk()->assertSee('Matching Office')->assertDontSee('Other Office')
            ->assertDontSee('workspace-sync-config')->assertDontSee('<html', false);
    }

    public function test_stale_or_missing_versions_cannot_overwrite_a_newer_branch_even_within_one_second(): void
    {
        $this->actingAs($this->reviewer());
        $branch = Branch::factory()->create(['latitude' => 14.5995, 'longitude' => 120.9842, 'geofence_radius_meters' => 150]);
        $data = $branch->toArray();
        $token = EditVersion::token($branch);
        $this->put('/hr/branches/'.$branch->id, [...$data, '_edit_version' => $token, 'branch_name' => 'Saved elsewhere'])->assertSessionHasNoErrors();

        $this->from('/hr/branches/'.$branch->id.'/edit')->put('/hr/branches/'.$branch->id, [...$data, '_edit_version' => $token, 'branch_name' => 'Unsaved stale draft'])
            ->assertSessionHasErrors('_edit_version')->assertSessionHasInput('branch_name', 'Unsaved stale draft');
        $this->put('/hr/branches/'.$branch->id, [...$data, 'branch_name' => 'Missing version'])->assertSessionHasErrors('_edit_version');
        $this->assertSame('Saved elsewhere', $branch->fresh()->branch_name);
    }

    public function test_queue_failure_after_commit_preserves_the_record_and_polling_revision(): void
    {
        $hr = $this->reviewer();
        $this->actingAs($hr);
        $data = Branch::factory()->make(['latitude' => 14.5995, 'longitude' => 120.9842, 'geofence_radius_meters' => 150])->toArray();
        Exceptions::fake();
        Queue::shouldReceive('connection')->andThrow(new \RuntimeException('Synthetic queue outage'));

        $this->post('/hr/branches', $data)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('branches', ['branch_code' => $data['branch_code']]);
        $this->getJson('/realtime/state')->assertOk()->assertJsonStructure(['revisions' => ['branches']]);
        Exceptions::assertReported(\RuntimeException::class);
    }

    public function test_background_reads_preserve_flash_feedback_for_the_next_navigation(): void
    {
        $this->actingAs($this->reviewer());

        $this->withSession(['status' => 'Saved successfully.', '_flash' => ['old' => ['status'], 'new' => []]])
            ->getJson('/realtime/state')->assertOk();

        $this->assertSame('Saved successfully.', session('status'));
    }

    public function test_document_purge_invalidates_the_owner_and_supervisors_leave_views(): void
    {
        $employee = $this->attendee();
        $supervisor = User::factory()->create(['role_id' => Role::SUPERVISOR]);
        $leave = LeaveRequest::factory()->create(['employee_id' => $employee->id, 'assigned_supervisor_user_id' => $supervisor->id]);
        $document = LeaveSupportingDocument::factory()->create(['leave_request_id' => $leave->id, 'purged_at' => now()]);
        Queue::fake([BroadcastWorkspaceChange::class]);

        app(AuditService::class)->record('leave.document_purged', $document);

        $this->assertDatabaseHas('workspace_revisions', ['user_id' => $employee->user_id, 'topic' => 'leave']);
        $this->assertDatabaseHas('workspace_revisions', ['user_id' => $supervisor->id, 'topic' => 'leave']);
        Queue::assertPushed(BroadcastWorkspaceChange::class, fn ($job) => $job->userId === $employee->user_id);
    }

    public function test_supervisor_and_leave_policy_edits_reject_stale_versions(): void
    {
        $this->actingAs($this->reviewer())->withSession(['auth.password_confirmed_at' => now()->timestamp]);
        $supervisor = User::factory()->create(['role_id' => Role::SUPERVISOR]);
        $version = EditVersion::token($supervisor);
        $data = ['first_name' => 'Synthetic', 'last_name' => 'Supervisor', 'email' => $supervisor->email, 'account_status' => 'active', '_edit_version' => $version];
        $this->put('/hr/supervisors/'.$supervisor->id, $data)->assertSessionHasNoErrors();
        $this->put('/hr/supervisors/'.$supervisor->id, [...$data, 'first_name' => 'Stale'])->assertSessionHasErrors('_edit_version');
        $this->assertSame('Synthetic Supervisor', $supervisor->fresh()->name);

        $type = LeaveType::factory()->create();
        $data = [...$type->toArray(), '_edit_version' => EditVersion::token($type), 'name' => 'Updated policy'];
        $this->put('/hr/leave/types/'.$type->id, $data)->assertSessionHasNoErrors();
        $this->put('/hr/leave/types/'.$type->id, [...$data, 'name' => 'Stale policy'])->assertSessionHasErrors('_edit_version');
        $this->assertSame('Updated policy', $type->fresh()->name);
    }
}

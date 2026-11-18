<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectionRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Role;
use App\Models\SupervisorAssignment;
use App\Models\User;
use App\Notifications\WorkflowNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationDropdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_notifications_are_limited_and_read_all_is_owner_only(): void
    {
        $employee = Employee::factory()->create()->user;
        $other = Employee::factory()->create()->user;
        $otherNotice = $this->notice($other, 'leave', '/employee/leave/1', 1);
        for ($index = 1; $index <= 11; $index++) {
            $this->notice($employee, 'leave', '/employee/leave/'.$index, $index)
                ->forceFill(['created_at' => now()->addSeconds($index)])->save();
        }

        $this->actingAs($employee)->getJson(route('notifications.dropdown.index'))
            ->assertOk()->assertJsonCount(10, 'items')->assertJsonPath('unread', 11)
            ->assertJsonPath('items.0.title', 'Workflow update 11')
            ->assertJsonMissingPath('items.0.url');
        $this->postJson(route('notifications.dropdown.read', $otherNotice))->assertNotFound();
        $this->postJson(route('notifications.dropdown.read-all'))->assertOk()->assertJsonPath('unread', 0);
        $this->assertNull($otherNotice->fresh()->read_at);
        $this->assertSame(0, $employee->unreadNotifications()->count());
    }

    public function test_open_marks_read_and_only_returns_an_authorized_local_destination(): void
    {
        $leave = LeaveRequest::factory()->create();
        $employee = $leave->employee->user;
        $url = route('employee.leave.show', $leave);
        $notice = $this->notice($employee, 'leave', $url, $leave->id);
        $invalid = $this->notice($employee, 'leave', 'https://example.test/elsewhere', $leave->id);
        $privateLeave = LeaveRequest::factory()->create();
        $private = $this->notice($employee, 'leave', route('employee.leave.show', $privateLeave), $privateLeave->id);

        $this->actingAs($employee)->postJson(route('notifications.dropdown.read', $notice), ['open' => true])
            ->assertOk()->assertJsonPath('destination', route('employee.leave.show', $leave, false))->assertJsonPath('unread', 2);
        $this->assertNotNull($notice->fresh()->read_at);
        foreach ([$invalid, $private] as $unavailable) {
            $this->postJson(route('notifications.dropdown.read', $unavailable), ['open' => true])
                ->assertOk()->assertJsonPath('destination', null)->assertJsonPath('message', 'This notification’s destination is no longer available.');
        }
    }

    public function test_supervisor_feed_and_bulk_read_are_leave_only_and_open_rechecks_assignment(): void
    {
        $employee = Employee::factory()->create();
        $supervisor = User::factory()->create(['role_id' => Role::SUPERVISOR, 'supervisor_department_id' => $employee->department_id]);
        $assignment = SupervisorAssignment::factory()->create(['employee_id' => $employee->id, 'supervisor_user_id' => $supervisor->id]);
        $leave = LeaveRequest::factory()->create(['employee_id' => $employee->id, 'assigned_supervisor_user_id' => $supervisor->id]);
        $notice = $this->notice($supervisor, 'leave', route('supervisor.leave.show', $leave), $leave->id);
        $correction = $this->notice($supervisor, 'correction', '/hr/attendance/corrections/1', 1);
        $this->actingAs($supervisor)->getJson(route('notifications.dropdown.index'))
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $notice->id);
        $this->postJson(route('notifications.dropdown.read', $correction))->assertNotFound();
        $this->postJson(route('notifications.dropdown.read', $notice), ['open' => true])
            ->assertOk()->assertJsonPath('destination', route('supervisor.leave.show', $leave, false));
        $assignment->update(['is_active' => false, 'revoked_at' => now()]);
        $this->postJson(route('notifications.dropdown.read', $notice), ['open' => true])
            ->assertOk()->assertJsonPath('destination', null);
        $this->postJson(route('notifications.dropdown.read-all'))->assertOk()->assertJsonPath('unread', 0);
        $this->assertNull($correction->fresh()->read_at);
    }

    public function test_hr_receives_own_correction_notification_and_all_roles_share_the_bell(): void
    {
        $hr = User::factory()->create(['role_id' => Role::HR]);
        $employee = Employee::factory()->create()->user;
        $supervisor = User::factory()->create(['role_id' => Role::SUPERVISOR]);
        $correction = AttendanceCorrectionRequest::factory()->create();
        $notice = $this->notice($hr, 'correction', route('hr.corrections.show', $correction), $correction->id);

        foreach ([$hr, $employee, $supervisor] as $actor) {
            $this->actingAs($actor)->get(route($actor->workspaceRoute()))->assertOk()
                ->assertSee('aria-controls="topbar-notifications"', false)
                ->assertDontSee('>Leave Notifications</a>', false);
        }
        $this->getJson(route('notifications.dropdown.index'))->assertOk()->assertJsonCount(0, 'items');
        $this->actingAs($hr)->getJson(route('notifications.dropdown.index'))
            ->assertOk()->assertJsonPath('items.0.id', $notice->id);
        $this->postJson(route('notifications.dropdown.read', $notice), ['open' => true])
            ->assertOk()->assertJsonPath('destination', route('hr.corrections.show', $correction, false));
    }

    private function notice(User $user, string $category, string $url, int $recordId): DatabaseNotification
    {
        $notice = new WorkflowNotice($category, 'Workflow update '.$recordId, $url, $recordId);
        $notice->id = (string) Str::uuid();
        $user->notify($notice);

        return $user->notifications()->findOrFail($notice->id);
    }
}

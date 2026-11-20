<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PrivacyNotice;
use App\Models\PrivacyNoticeAcknowledgement;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_login_for_each_role_requires_the_exact_current_version(): void
    {
        config(['privacy.enabled' => true]);
        $notice = PrivacyNotice::factory()->published()->create(['requires_acknowledgement' => true]);

        foreach ([Role::HR, Role::SUPERVISOR, Role::EMPLOYEE] as $role) {
            $user = User::factory()->create(['role_id' => $role]);
            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('privacy.index'));
            $this->get('/dashboard')->assertRedirect(route('privacy.index'));
            $this->get(route(match ($role) {
                Role::HR => 'hr.leave.index',
                Role::SUPERVISOR => 'supervisor.leave.index',
                Role::EMPLOYEE => 'employee.leave.index',
            }))->assertRedirect(route('privacy.index'));
            $this->get(route('leave.verify.scan'))->assertRedirect(route('privacy.index'));
            $this->get(route('privacy.index'))->assertOk()->assertSee('Read Full Privacy Notice');
            $this->get(route('privacy.show', $notice))->assertOk()->assertSee('Information collected');
            $this->getJson('/realtime/state')->assertForbidden();
            $this->post(route('privacy.acknowledge'), ['notice_id' => $notice->id])->assertSessionHasErrors('acknowledged');
            $payload = ['notice_id' => $notice->id, 'acknowledged' => '1'];
            $this->post(route('privacy.acknowledge'), $payload)->assertRedirect(route($user->workspaceRoute()));
            $this->post(route('privacy.acknowledge'), $payload)->assertRedirect(route($user->workspaceRoute()));
            $this->get('/dashboard')->assertRedirect(route($user->workspaceRoute()));
            $this->assertDatabaseHas('privacy_notice_acknowledgements', ['user_id' => $user->id, 'privacy_notice_id' => $notice->id]);
            $this->post('/logout')->assertRedirect();
            $this->assertGuest();
        }

        $this->assertDatabaseCount('privacy_notice_acknowledgements', 3);
        $this->assertSame(3, AuditLog::query()->where('action', 'privacy.notice.acknowledged')->whereNull('ip_address')->whereNull('user_agent')->count());
    }

    public function test_drafts_and_future_notices_are_hidden_and_an_effective_revision_reopens_the_gate(): void
    {
        config(['privacy.enabled' => true]);
        $user = User::factory()->create();
        $previous = PrivacyNotice::factory()->published()->create(['requires_acknowledgement' => true]);
        PrivacyNoticeAcknowledgement::factory()->for($user)->for($previous)->create();
        $draft = PrivacyNotice::factory()->create();
        $future = PrivacyNotice::factory()->published()->create(['effective_at' => now()->addDay(), 'requires_acknowledgement' => true]);
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route($user->workspaceRoute()));
        $this->get(route('privacy.show', $draft))->assertNotFound();
        $this->get(route('privacy.show', $future))->assertNotFound();
        $this->travel(2)->days();
        $this->get('/dashboard')->assertRedirect(route('privacy.index'));
        $this->post(route('privacy.acknowledge'), ['notice_id' => $previous->id, 'acknowledged' => '1'])->assertConflict();
        $this->post(route('privacy.acknowledge'), ['notice_id' => $draft->id, 'acknowledged' => '1'])->assertConflict();
        $this->get(route('privacy.show', $previous))->assertOk();
        $this->assertDatabaseCount('privacy_notice_acknowledgements', 1);
    }

    public function test_only_configured_hr_with_password_confirmation_can_publish_the_reviewed_revision(): void
    {
        config(['privacy.enabled' => true]);
        $publisher = User::factory()->create(['role_id' => Role::HR]);
        config(['privacy.publisher_ids' => [$publisher->id]]);
        $otherHr = User::factory()->create(['role_id' => Role::HR]);
        $supervisor = User::factory()->create(['role_id' => Role::SUPERVISOR]);
        $employee = User::factory()->create();
        $notice = PrivacyNotice::factory()->create(['content' => "## Processing\n<script>bad()</script>"]);
        $publish = route('hr.privacy.publish', $notice);
        $approval = ['approval_reference' => 'TEST-APPROVAL', 'approved' => '1', 'revision' => $notice->draftFingerprint()];

        foreach ([$otherHr, $supervisor, $employee] as $user) {
            $this->actingAs($user)->post($publish, $approval)->assertForbidden();
        }

        $this->actingAs($publisher)->get(route('hr.privacy.index'))->assertRedirect(route('password.confirm'));
        $this->withSession(['auth.password_confirmed_at' => time()]);
        $this->get(route('hr.privacy.index'))->assertOk();
        $draftPayload = [
            'version' => '1.0-review',
            'title' => 'Draft notice',
            'content' => 'Awaiting authorized review.',
            'effective_at' => now()->format('Y-m-d\TH:i'),
        ];
        $this->post(route('hr.privacy.store'), $draftPayload)->assertRedirect()->assertSessionHasNoErrors();
        $draft = PrivacyNotice::query()->where('version', '1.0-review')->sole();
        $this->assertNull($draft->published_at);
        $this->patch(route('hr.privacy.update', $draft), array_replace($draftPayload, ['title' => 'Edited draft']))->assertRedirect();
        $this->assertSame('Edited draft', $draft->fresh()->title);

        $this->get(route('hr.privacy.show', $notice))->assertOk();
        $this->post($publish, array_replace($approval, ['revision' => str_repeat('0', 64)]))->assertConflict();
        $this->post($publish, $approval)->assertRedirect();
        $this->assertNotNull($notice->fresh()->published_at);
        $this->patch(route('hr.privacy.update', $notice), [
            'version' => $notice->version, 'title' => 'Changed', 'content' => 'Changed', 'effective_at' => now()->format('Y-m-d\\TH:i'),
        ])->assertConflict();
        $this->post(route('hr.privacy.archive', $notice))->assertConflict();
        PrivacyNotice::factory()->published()->create(['effective_at' => now()]);
        $this->post(route('hr.privacy.archive', $notice))->assertRedirect();
        $this->assertNotNull($notice->fresh()->archived_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'privacy.notice.published', 'actor_user_id' => $publisher->id, 'subject_id' => $notice->id]);
        $this->actingAs($employee)->get(route('privacy.show', $notice))->assertOk()->assertSee('<script>bad()</script>')->assertDontSee('<script>bad()</script>', false)->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_pending_acknowledgement_preserves_logout_and_the_existing_qr_continuation(): void
    {
        config(['privacy.enabled' => true]);
        $user = User::factory()->create();
        $notice = PrivacyNotice::factory()->published()->create(['requires_acknowledgement' => true]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('privacy.index'));
        $this->post('/logout')->assertRedirect();
        $this->assertGuest();
        $this->assertDatabaseCount('privacy_notice_acknowledgements', 0);

        $this->actingAs($user)->withSession(['leave_scan_hash' => str_repeat('a', 64)])
            ->get(route('leave.verify.scan'))->assertRedirect(route('privacy.index'));
        $this->post(route('privacy.acknowledge'), ['notice_id' => $notice->id, 'acknowledged' => '1'])
            ->assertRedirect(route('leave.verify.scan'));
    }

    public function test_privacy_center_only_lists_the_requesting_users_acknowledgements(): void
    {
        config(['privacy.enabled' => true]);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $notice = PrivacyNotice::factory()->published()->create(['contact_email' => 'privacy@example.test']);
        PrivacyNoticeAcknowledgement::factory()->for($other)->for($notice)->create();
        $this->get(route('privacy.index'))->assertRedirect(route('login'));
        $this->actingAs($user)->get(route('privacy.index'))->assertOk()->assertSee('You have no recorded acknowledgements.')->assertSee('mailto:privacy@example.test');
        $this->post(route('privacy.acknowledge'), ['user_id' => $other->id, 'notice_id' => $notice->id, 'acknowledged' => '1'])->assertRedirect(route($user->workspaceRoute()));
        $this->assertDatabaseHas('privacy_notice_acknowledgements', ['user_id' => $user->id, 'privacy_notice_id' => $notice->id]);
        config(['privacy.enabled' => false]);
        $this->get('/dashboard')->assertRedirect(route($user->workspaceRoute()));
        $this->get(route('privacy.show', $notice))->assertNotFound();
    }
}

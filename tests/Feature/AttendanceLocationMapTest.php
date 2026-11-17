<?php

namespace Tests\Feature;

use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AttendanceData;
use Tests\TestCase;

class AttendanceLocationMapTest extends TestCase
{
    use AttendanceData, RefreshDatabase;

    /** @return list<array<string, mixed>> */
    private function maps(TestResponse $response): array
    {
        preg_match_all('/data-map-config="([^"]+)"/', $response->getContent(), $matches);

        return array_map(fn (string $json): array => json_decode(html_entity_decode($json, ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR), $matches[1]);
    }

    public function test_event_specific_saved_locations_photos_and_distinct_manila_timestamps_are_visible_to_owner_and_hr(): void
    {
        $this->travelTo(new \DateTimeImmutable('2026-11-17T00:25:00Z'));
        $employee = $this->attendee();
        $this->punch($this->capture($employee), ['retrieved_at' => now()->subSeconds(2)->valueOf()])->assertOk();
        $this->travel(2)->hours();
        $this->punch($this->capture($employee, 'time_out'), ['latitude' => 14.5996], 140)->assertOk();
        $record = AttendanceRecord::sole();

        foreach ([$employee->user, $this->reviewer()] as $viewer) {
            $response = $this->actingAs($viewer)->get(route('attendance.show', $record))->assertOk()
                ->assertSee('Attendance Location')->assertSee('Within Geofence')->assertSee('5 metres')->assertSee('0 metres')
                ->assertSee('Nov 17, 2026 08:24:58 Asia/Manila')->assertSee('Nov 17, 2026 08:25:00 Asia/Manila')
                ->assertSee('Technical Attendance Location Details')->assertDontSee('Employee-reported, untrusted coordinates');
            $maps = $this->maps($response);
            $this->assertCount(1, $maps);
            $this->assertSame([14.5995, 120.9842], $maps[0]['events'][0]['employee']);
            $this->assertSame([14.5996, 120.9842], $maps[0]['events'][1]['employee']);
            $this->assertSame([14.5995, 120.9842], $maps[0]['branch']);
            $this->assertSame(150, $maps[0]['radius']);
            $this->assertSame('Time In', $maps[0]['events'][0]['action']);
            $this->assertSame('Time Out', $maps[0]['events'][1]['action']);
            foreach ($record->events as $event) {
                $response->assertSee(route('attendance.photo', $event->photo));
            }
        }
    }

    public function test_branch_edits_and_effective_time_corrections_never_replace_historical_location_evidence(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $event = AttendanceEvent::sole();
        $original = $event->getAttributes();
        $employee->currentAssignment->branch->update(['branch_name' => 'Changed current branch', 'latitude' => 13.9, 'longitude' => 121.1, 'geofence_radius_meters' => 80]);
        $event->record->forceFill(['correction_version' => 1, 'effective_time_in_at' => now()->subHour()])->save();

        $response = $this->actingAs($this->reviewer())->get(route('attendance.show', $event->record))->assertOk()->assertSee('manual HR correction');
        $map = $this->maps($response)[0];
        $this->assertSame([14.5995, 120.9842], $map['branch']);
        $this->assertSame(150, $map['radius']);
        $this->assertNotSame('Changed current branch', $map['branchName']);
        $this->assertSame($original, $event->fresh()->getAttributes());
    }

    public function test_failed_geofence_keeps_its_stored_status_distance_and_accuracy(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee), ['latitude' => 14.7, 'request_review' => 1, 'exception_reason' => 'Synthetic GPS reading requires review.'])->assertOk();
        $event = AttendanceEvent::sole();

        $response = $this->actingAs($this->reviewer())->get(route('attendance.show', $event->record))->assertOk()->assertSee('Outside Geofence')->assertSee($event->distance_meters.' metres');
        $this->assertSame([14.7, 120.9842], $this->maps($response)[0]['events'][0]['employee']);
        $this->assertSame('outside_geofence', $event->fresh()->geofence_status);
    }

    public static function unavailableEvidence(): array
    {
        return [
            'missing location' => [null, null, null],
            'invalid latitude' => [['latitude' => 91, 'longitude' => 121, 'retrieved_at' => 'invalid'], null, null],
            'invalid longitude' => [['latitude' => 14, 'longitude' => -181], null, null],
            'empty latitude is not zero' => [['latitude' => '', 'longitude' => 0], null, null],
            'invalid historical configuration' => [['latitude' => 0, 'longitude' => 0], ['latitude' => null, 'longitude' => null, 'radius' => -1], [0, 0]],
        ];
    }

    #[DataProvider('unavailableEvidence')]
    public function test_missing_or_invalid_saved_evidence_is_not_fabricated(?array $location, ?array $snapshot, ?array $expectedPoint): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $event = AttendanceEvent::sole();
        $event->update(['location' => $location, 'branch_snapshot' => $snapshot ?? $event->branch_snapshot]);

        $response = $this->get(route('attendance.show', $event->record))->assertOk()->assertSee('Not available');
        $map = $this->maps($response)[0];
        $this->assertSame($expectedPoint, $map['events'][0]['employee']);
        if ($expectedPoint === null) {
            $response->assertSee('Attendance location data is unavailable.');
        }
        if ($snapshot !== null) {
            $this->assertNull($map['branch']);
            $this->assertNull($map['radius']);
            $response->assertSee('Historical branch coordinates are unavailable.')->assertSee('Historical geofence radius unavailable');
        }
    }

    public function test_purged_locations_and_legacy_missing_snapshots_are_not_replaced_with_current_settings(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $event = AttendanceEvent::sole();
        $event->update(['location_purged_at' => now(), 'branch_snapshot' => ['branch_name' => 'Legacy branch']]);

        $response = $this->get(route('attendance.show', $event->record))->assertOk()->assertSee('Attendance location data is unavailable.')->assertSee('Historical branch coordinates are unavailable.')->assertDontSee('x-ref="map"', false);
        $map = $this->maps($response)[0];
        $this->assertNull($map['events'][0]['employee']);
        $this->assertNull($map['branch']);
        $this->assertNull($map['radius']);
        $response->assertDontSee('14.5995');
    }

    public function test_maps_use_existing_evidence_authorization_and_do_not_appear_on_monitoring_rows(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $record = AttendanceRecord::sole();
        $this->actingAs($this->attendee()->user)->get(route('attendance.show', $record))->assertForbidden()->assertDontSee('data-map-config');
        $this->actingAs(User::factory()->create(['role_id' => Role::SUPERVISOR]))->get(route('attendance.show', $record))->assertForbidden()->assertDontSee('data-map-config');
        $this->actingAs($this->reviewer())->get('/hr/attendance')->assertOk()->assertDontSee('data-map-config');
        $this->post('/logout');
        $this->get(route('attendance.show', $record))->assertRedirect('/login');
    }

    public function test_popup_data_and_technical_details_escape_untrusted_saved_text(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $event = AttendanceEvent::sole();
        $snapshot = $event->branch_snapshot;
        $snapshot['branch_name'] = '<img src=x onerror=alert(1)>';
        $event->update(['branch_snapshot' => $snapshot]);
        $employee->update(['first_name' => '<script>alert(1)</script>']);

        $response = $this->get(route('attendance.show', $event->record))->assertOk()->assertSee($snapshot['branch_name'])->assertDontSee($snapshot['branch_name'], false)->assertDontSee('<script>alert(1)</script>', false);
        $this->assertSame($snapshot['branch_name'], $this->maps($response)[0]['branchName']);
    }

    public function test_missing_historical_radius_does_not_borrow_the_current_radius(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $event = AttendanceEvent::sole();
        $snapshot = $event->branch_snapshot;
        unset($snapshot['radius']);
        $event->update(['branch_snapshot' => $snapshot]);

        $response = $this->get(route('attendance.show', $event->record))->assertOk()->assertSee('Historical geofence radius unavailable');
        $this->assertSame([14.5995, 120.9842], $this->maps($response)[0]['branch']);
        $this->assertNull($this->maps($response)[0]['radius']);
    }

    public function test_unusable_device_timestamps_remain_unavailable_and_do_not_change_server_time(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $event = AttendanceEvent::sole();
        $original = $event->occurred_at->toIso8601String();
        foreach (['not-a-date', '9999999999999999999', -1] as $timestamp) {
            $location = $event->location;
            $location['retrieved_at'] = $timestamp;
            $event->update(['location' => $location, 'geofence_status' => 'stale_location']);
            $response = $this->get(route('attendance.show', $event->record))->assertOk()->assertSee('Location Requires Review')->assertSee('Not available');
            $this->assertSame($timestamp, $event->fresh()->location['retrieved_at']);
            $this->assertSame($original, $event->fresh()->occurred_at->toIso8601String());
        }
    }

    public function test_single_events_have_one_shared_map_and_an_explicit_missing_photo_card(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $event = AttendanceEvent::sole();
        $response = $this->get(route('attendance.show', $event->record))->assertOk()->assertSee('No Time Out submission recorded.');
        $this->assertCount(1, $this->maps($response));
        $this->assertSame(['time_in'], array_column($this->maps($response)[0]['events'], 'type'));
        $event->update(['event_type' => 'time_out']);
        $response = $this->get(route('attendance.show', $event->record))->assertOk()->assertSee('No Time In submission recorded.');
        $this->assertCount(1, $this->maps($response));
        $this->assertSame(['time_out'], array_column($this->maps($response)[0]['events'], 'type'));
    }

    public function test_shared_circle_identifies_differing_historical_snapshots_and_keeps_each_result(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $employee->currentAssignment->branch->update(['geofence_radius_meters' => 80]);
        $this->punch($this->capture($employee, 'time_out'), ['latitude' => 14.7, 'request_review' => 1, 'exception_reason' => 'Synthetic checkout outside the recorded geofence.'], 140)->assertOk();
        $response = $this->actingAs($this->reviewer())->get(route('attendance.show', AttendanceRecord::sole()))->assertOk()->assertSee('Within Geofence')->assertSee('Outside Geofence')->assertSee('different recorded geofence settings')->assertSee('80 metres')->assertSee('150 metres');
        $map = $this->maps($response)[0];
        $this->assertSame(150, $map['radius']);
        $this->assertSame(80, $map['events'][1]['radius']);
        $this->assertSame('Time In', $map['branchSource']);
        $response->assertSee(route('hr.attendance.review', AttendanceEvent::where('event_type', 'time_out')->sole()));
    }

    public function test_rejected_attempts_remain_accessible_without_replacing_the_accepted_photo_or_creating_extra_maps(): void
    {
        $employee = $this->attendee();
        $this->punch($this->capture($employee))->assertOk();
        $this->punch($this->capture($employee, 'time_out'), ['latitude' => 14.7, 'request_review' => 1, 'exception_reason' => 'Synthetic rejected checkout evidence.'], 140)->assertOk();
        $rejected = AttendanceEvent::where('event_type', 'time_out')->sole();
        $this->actingAs($this->reviewer())->post(route('hr.attendance.review', $rejected), ['decision' => 'rejected', 'remarks' => 'Rejected synthetic location evidence.', 'confirmation' => 1])->assertRedirect();
        $this->punch($this->capture($employee, 'time_out'), ['latitude' => 14.5996], 230)->assertOk();
        $response = $this->get(route('attendance.show', AttendanceRecord::sole()))->assertOk()->assertSee('Additional submissions (1)')->assertSee(route('attendance.photo', $rejected->photo))->assertSee('Rejected synthetic location evidence.');
        $maps = $this->maps($response);
        $this->assertCount(1, $maps);
        $this->assertCount(2, $maps[0]['events']);
        $this->assertSame([14.5996, 120.9842], $maps[0]['events'][1]['employee']);
        $this->assertNotSame($rejected->id, $maps[0]['events'][1]['eventId']);
    }
}

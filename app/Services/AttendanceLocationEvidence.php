<?php

namespace App\Services;

use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use Carbon\CarbonImmutable;

class AttendanceLocationEvidence
{
    /** @return array{map: array<string, mixed>, details: array<string, string>, technical: array<string, string>, status: string, tone: string} */
    public function present(AttendanceEvent $event, string $employeeName): array
    {
        $location = $event->location_purged_at ? [] : ($event->location ?? []);
        $branch = $event->branch_snapshot ?? [];
        $employeePoint = $this->point($location);
        $branchPoint = $this->point($branch);
        $radius = $this->number($branch['radius'] ?? null);
        $radius = $radius !== null && $radius > 0 ? $radius : null;
        $accuracy = $this->number($event->accuracy_meters);
        $distance = $this->number($event->distance_meters);
        $retrievedAt = $this->number($location['retrieved_at'] ?? null);
        $capturedAt = $retrievedAt !== null && $retrievedAt > 0 && $retrievedAt <= 253402300799999 && floor($retrievedAt) === $retrievedAt
            ? CarbonImmutable::createFromTimestampMs((int) $retrievedAt, 'UTC')->timezone('Asia/Manila')->format('M j, Y H:i:s').' Asia/Manila' : 'Not available';
        $recordedAt = $event->occurred_at ? $event->occurred_at->copy()->timezone('Asia/Manila')->format('M j, Y H:i:s').' Asia/Manila' : 'Not available';
        $status = match ($event->geofence_status) {
            'within_geofence' => 'Within Geofence',
            'outside_geofence' => 'Outside Geofence',
            'location_unavailable', 'location_error' => 'Location Data Unavailable',
            default => 'Location Requires Review',
        };
        $tone = match ($event->geofence_status) {
            'within_geofence' => 'teal',
            'outside_geofence' => 'danger',
            default => 'warning',
        };

        return [
            'map' => [
                'eventId' => $event->id,
                'employee' => $employeePoint,
                'branch' => $branchPoint,
                'radius' => $radius,
                'branchName' => $this->text($branch['branch_name'] ?? null),
                'employeeName' => $employeeName,
                'action' => ucwords(str_replace('_', ' ', $event->event_type)),
                'accuracy' => $accuracy !== null && $accuracy >= 0 ? $accuracy : null,
                'recordedAt' => $recordedAt,
                'capturedAt' => $capturedAt,
                'tileUrl' => config('maps.tile_url'),
                'attribution' => config('maps.tile_attribution'),
                'maxZoom' => config('maps.max_zoom'),
            ],
            'details' => [
                'Original geofence result' => str_replace('_', ' ', $event->geofence_status),
                'Distance from branch (recorded)' => $distance !== null && $distance >= 0 ? $distance.' metres' : 'Not available',
                'Allowed radius (recorded)' => $radius !== null ? $radius.' metres' : 'Historical geofence radius unavailable',
                'GPS accuracy (device-reported estimate)' => $accuracy !== null && $accuracy >= 0 ? $accuracy.' metres' : 'Not available',
                'Location Captured At (Device-Reported)' => $capturedAt,
                'Attendance Recorded At (Server-Recorded)' => $recordedAt,
            ],
            'technical' => [
                'Recorded branch' => $this->text($branch['branch_name'] ?? null),
                'Reported latitude' => $this->text($location['latitude'] ?? null),
                'Reported longitude' => $this->text($location['longitude'] ?? null),
                'Device location timestamp (milliseconds)' => $this->text($location['retrieved_at'] ?? null),
                'Recorded branch latitude' => $this->text($branch['latitude'] ?? null),
                'Recorded branch longitude' => $this->text($branch['longitude'] ?? null),
                'Recorded radius (metres)' => $this->text($branch['radius'] ?? null),
            ],
            'status' => $status,
            'tone' => $tone,
        ];
    }

    /** @return array{entries: array<string, array{event: ?AttendanceEvent, evidence: ?array}>, additional: list<array{event: AttendanceEvent, evidence: array}>, map: array<string, mixed>, branchSource: ?string, differentSnapshots: bool} */
    public function day(AttendanceRecord $record): array
    {
        $events = $record->events->sortBy('id');
        $entries = [];
        foreach (['time_in', 'time_out'] as $type) {
            $candidates = $events->where('event_type', $type);
            $event = $candidates->whereIn('verification_status', ['location_validated', 'exception_approved'])->first() ?? $candidates->last();
            $entries[$type] = ['event' => $event, 'evidence' => $event ? $this->present($event, $record->employee->full_name) : null];
        }
        $available = array_filter($entries, fn (array $entry): bool => $entry['event'] !== null);
        $source = array_key_first($available);
        foreach ($available as $type => $entry) {
            if ($entry['evidence']['map']['branch'] !== null) {
                $source = $type;
                break;
            }
        }
        foreach ($available as $type => $entry) {
            if ($entry['evidence']['map']['branch'] !== null && $entry['evidence']['map']['radius'] !== null) {
                $source = $type;
                break;
            }
        }
        $branchMap = $source ? $entries[$source]['evidence']['map'] : null;
        $geometry = fn (?array $map): array => [$map['branch'] ?? null, $map['radius'] ?? null];
        $different = count($available) === 2 && $geometry($entries['time_in']['evidence']['map']) !== $geometry($entries['time_out']['evidence']['map']);
        $selectedIds = array_map(fn (array $entry): int => $entry['event']->id, $available);

        return [
            'entries' => $entries,
            'additional' => $events->reject(fn (AttendanceEvent $event): bool => in_array($event->id, $selectedIds, true))->map(fn (AttendanceEvent $event): array => ['event' => $event, 'evidence' => $this->present($event, $record->employee->full_name)])->values()->all(),
            'map' => [
                'recordId' => $record->id,
                'branch' => $branchMap['branch'] ?? null,
                'radius' => $branchMap['radius'] ?? null,
                'branchName' => $branchMap['branchName'] ?? 'Not available',
                'branchSource' => $source ? ucwords(str_replace('_', ' ', $source)) : null,
                'events' => array_values(array_map(fn (array $entry, string $type): array => ['type' => $type, ...$entry['evidence']['map']], $available, array_keys($available))),
                'tileUrl' => config('maps.tile_url'),
                'attribution' => config('maps.tile_attribution'),
                'maxZoom' => config('maps.max_zoom'),
            ],
            'branchSource' => $source,
            'differentSnapshots' => $different,
        ];
    }

    /** @param array<string, mixed> $data
     * @return array{float, float}|null
     */
    private function point(array $data): ?array
    {
        $latitude = $this->number($data['latitude'] ?? null);
        $longitude = $this->number($data['longitude'] ?? null);

        return $latitude !== null && $longitude !== null && abs($latitude) <= 90 && abs($longitude) <= 180 ? [$latitude, $longitude] : null;
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) && is_finite((float) $value) ? (float) $value : null;
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : 'Not available';
    }
}

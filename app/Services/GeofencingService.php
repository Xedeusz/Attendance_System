<?php

namespace App\Services;

class GeofencingService
{
    public function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lon2 - $lon1) / 2) ** 2;

        return 6371008.8 * 2 * asin(sqrt(max(0, min(1, $a))));
    }

    /** Browser evidence is untrusted; this evaluates a configured business rule only. */
    public function evaluate(array $branch, array $location, int $nowMs): array
    {
        $status = 'location_unavailable';
        $distance = null;
        if ($branch['latitude'] === null || $branch['longitude'] === null || ! $branch['radius']) {
            return ['status' => 'branch_not_configured', 'distance' => null, 'validated' => false];
        }
        if (isset($location['latitude'], $location['longitude'], $location['accuracy'], $location['retrieved_at'])) {
            $lat = (float) $location['latitude'];
            $lon = (float) $location['longitude'];
            $accuracy = (float) $location['accuracy'];
            if (! is_finite($lat) || ! is_finite($lon) || ! is_finite($accuracy) || abs($lat) > 90 || abs($lon) > 180 || $accuracy < 0) {
                return ['status' => 'location_error', 'distance' => null, 'validated' => false];
            }
            $distance = $this->distance($lat, $lon, (float) $branch['latitude'], (float) $branch['longitude']);
            $age = $nowMs - (int) $location['retrieved_at'];
            $status = match (true) {
                $age > $branch['max_age_seconds'] * 1000 || $age < -15000 => 'stale_location',
                $accuracy > $branch['accuracy_limit'] => 'accuracy_insufficient',
                $distance > $branch['radius'] => 'outside_geofence',
                $distance + $accuracy > $branch['radius'] => 'boundary_uncertain',
                default => 'within_geofence',
            };
        }

        return ['status' => $status, 'distance' => $distance === null ? null : round($distance, 2), 'validated' => $status === 'within_geofence'];
    }
}

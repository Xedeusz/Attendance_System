<?php

namespace Tests\Unit;

use App\Services\GeofencingService;
use PHPUnit\Framework\TestCase;

class GeofencingTest extends TestCase
{
    public function test_haversine_handles_zero_equator_antimeridian_and_antipodes(): void
    {
        $geo = new GeofencingService;
        $this->assertEqualsWithDelta(0, $geo->distance(14.5, 121, 14.5, 121), 0.00001);
        $this->assertEqualsWithDelta(111195.08, $geo->distance(0, 0, 0, 1), 1);
        $this->assertEqualsWithDelta(222.39, $geo->distance(0, 179.999, 0, -179.999), 0.1);
        $this->assertEqualsWithDelta(20015114.44, $geo->distance(0, 0, 0, 180), 1);
    }

    public function test_location_policy_considers_accuracy_boundary_age_and_missing_data(): void
    {
        $geo = new GeofencingService;
        $branch = ['latitude' => 0, 'longitude' => 0, 'radius' => 100, 'max_age_seconds' => 45, 'accuracy_limit' => 50];
        $position = ['latitude' => 0, 'longitude' => 0, 'accuracy' => 5, 'retrieved_at' => 100000];
        $this->assertTrue($geo->evaluate($branch, $position, 100000)['validated']);
        foreach ([['longitude' => 0.002], ['accuracy' => 51], ['retrieved_at' => 1], ['retrieved_at' => 116000], ['latitude' => NAN], ['longitude' => 181]] as $overrides) {
            $this->assertFalse($geo->evaluate($branch, array_replace($position, $overrides), 100000)['validated']);
        }
        $boundary = array_replace($position, ['longitude' => rad2deg(98 / 6371008.8)]);
        $this->assertSame('boundary_uncertain', $geo->evaluate($branch, $boundary, 100000)['status']);
        $this->assertSame('location_unavailable', $geo->evaluate($branch, [], 100000)['status']);
        $this->assertSame('branch_not_configured', $geo->evaluate([...$branch, 'latitude' => null], $position, 100000)['status']);
    }
}

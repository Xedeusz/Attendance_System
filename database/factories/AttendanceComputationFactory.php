<?php

namespace Database\Factories;

use App\Models\AttendanceComputation;
use App\Models\AttendanceCorrectionRequest;
use App\Services\AttendancePolicyResolver;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttendanceComputation> */
class AttendanceComputationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'attendance_correction_request_id' => AttendanceCorrectionRequest::factory()->state(['status' => 'approved', 'pending_key' => null]),
            'version' => 1, 'trigger' => 'correction',
            'policy' => app(AttendancePolicyResolver::class)->withoutPolicy('2026-11-19', null, null),
            'holiday_types' => [], 'timestamps_used' => ['in' => null, 'out' => null], 'incomplete' => false,
        ];
    }
}

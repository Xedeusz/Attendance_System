<?php

namespace Database\Factories;

use App\Models\AttendanceCorrectionRequest;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttendanceCorrectionRequest> */
class AttendanceCorrectionRequestFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['employee_id' => Employee::factory(), 'correction_type' => 'missing_record', 'requested_date' => now()->subDay()->toDateString(), 'requested_time_in_at' => now()->subDay()->setTime(1, 0), 'requested_time_out_at' => now()->subDay()->setTime(9, 0), 'original_values' => [], 'record_revision' => 0, 'reason' => 'Synthetic missing attendance request for review.', 'status' => 'pending', 'pending_key' => fake()->unique()->sha256(), 'submitted_at' => now()];
    }
}

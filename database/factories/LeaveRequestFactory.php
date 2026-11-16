<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LeaveRequest> */
class LeaveRequestFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['employee_id' => Employee::factory(), 'leave_type_id' => LeaveType::factory(), 'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString(), 'requested_duration' => 1, 'reason' => 'Synthetic leave request for workflow testing.', 'type_snapshot' => ['name' => 'Synthetic Leave', 'requires_document' => false, 'affects_balance' => false, 'allows_half_day' => false, 'duration_policy' => 'requested_days'], 'status' => 'awaiting_supervisor_assignment', 'submitted_at' => now()];
    }
}

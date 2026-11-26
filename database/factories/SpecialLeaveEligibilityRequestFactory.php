<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\SpecialLeaveEligibilityRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpecialLeaveEligibilityRequest>
 */
class SpecialLeaveEligibilityRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['employee_id' => Employee::factory(), 'leave_type_id' => LeaveType::factory()->state(['code' => 'MATERNITY', 'name' => 'Maternity Leave', 'affects_balance' => true]), 'reference' => 'SLV-'.fake()->unique()->bothify('####-????????'), 'event_category' => 'actual_live_childbirth', 'actual_event_date' => now()->toDateString(), 'status' => 'pending_hr_review', 'additional_information' => 'Synthetic eligibility information for testing.', 'employee_declaration' => true, 'submitted_at' => now()];
    }
}

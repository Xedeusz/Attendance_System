<?php

namespace Database\Factories;

use App\Models\SpecialLeaveEligibilityActivity;
use App\Models\SpecialLeaveEligibilityRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpecialLeaveEligibilityActivity>
 */
class SpecialLeaveEligibilityActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['special_leave_eligibility_request_id' => SpecialLeaveEligibilityRequest::factory(), 'actor_user_id' => User::factory(), 'action' => 'submitted', 'previous_status' => null, 'new_status' => 'pending_hr_review', 'created_at' => now()];
    }
}

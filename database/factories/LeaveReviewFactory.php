<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\LeaveReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LeaveReview> */
class LeaveReviewFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['leave_request_id' => LeaveRequest::factory(), 'reviewer_user_id' => User::factory(), 'action' => 'submitted', 'remarks' => null, 'metadata' => [], 'reviewed_at' => now()];
    }
}

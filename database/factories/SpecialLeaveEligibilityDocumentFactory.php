<?php

namespace Database\Factories;

use App\Models\SpecialLeaveEligibilityDocument;
use App\Models\SpecialLeaveEligibilityRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpecialLeaveEligibilityDocument>
 */
class SpecialLeaveEligibilityDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['special_leave_eligibility_request_id' => SpecialLeaveEligibilityRequest::factory(), 'storage_path' => 'eligibility/'.fake()->uuid().'.bin', 'original_name' => 'supporting-document.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1024, 'uploaded_by_user_id' => User::factory()];
    }
}

<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\LeaveSupportingDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LeaveSupportingDocument> */
class LeaveSupportingDocumentFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['leave_request_id' => LeaveRequest::factory(), 'storage_path' => fake()->uuid().'.bin', 'original_name' => 'synthetic.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100];
    }
}

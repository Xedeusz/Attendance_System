<?php

namespace Database\Factories;

use App\Models\LeaveApprovalDocument;
use App\Models\LeaveRequest;
use App\Models\SupervisorSignature;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<LeaveApprovalDocument> */
class LeaveApprovalDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supervisor_signature_id' => SupervisorSignature::factory(),
            'approved_by_user_id' => fn (array $attributes): int => SupervisorSignature::findOrFail($attributes['supervisor_signature_id'])->supervisor_user_id,
            'leave_request_id' => fn (array $attributes): int => LeaveRequest::factory()->create([
                'status' => 'approved',
                'assigned_supervisor_user_id' => $attributes['approved_by_user_id'],
                'reviewed_at' => now(),
                'finalized_at' => now(),
            ])->id,
            'document_reference' => fn (array $attributes): string => 'LMPC-LA-'.$attributes['leave_request_id'].'-01',
            'storage_path' => 'leave-approval-documents/'.Str::uuid().'.bin',
            'sha256_hash' => hash('sha256', ''),
            'version' => 1,
            'template_reference' => 'HRD No. 23/Rev.2',
            'approver_name' => fn (array $attributes): string => User::findOrFail($attributes['approved_by_user_id'])->name,
            'generated_at' => now(),
        ];
    }
}

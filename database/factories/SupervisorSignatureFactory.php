<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\SupervisorSignature;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SupervisorSignature> */
class SupervisorSignatureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supervisor_user_id' => User::factory()->state(['role_id' => Role::SUPERVISOR]),
            'storage_path' => 'supervisor-signatures/'.Str::uuid().'.bin',
            'version' => 1,
            'registered_at' => now(),
        ];
    }
}

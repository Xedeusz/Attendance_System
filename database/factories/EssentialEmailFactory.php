<?php

namespace Database\Factories;

use App\Models\EssentialEmail;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EssentialEmail> */
class EssentialEmailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_key' => EssentialEmail::PASSWORD_RESET.':'.fake()->unique()->sha256(),
            'type' => EssentialEmail::PASSWORD_RESET,
            'recipient_user_id' => User::factory(),
            'recipient_email' => fn (array $attributes): string => User::findOrFail($attributes['recipient_user_id'])->email,
            'link_base' => 'https://limcoma.example.test',
            'status' => 'skipped',
            'expires_at' => now()->subMinute(),
        ];
    }
}

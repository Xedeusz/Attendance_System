<?php

namespace Database\Factories;

use App\Models\PrivacyNotice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PrivacyNotice> */
class PrivacyNoticeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'version' => fake()->unique()->numerify('test-######'),
            'title' => 'Synthetic privacy notice',
            'content' => "## Information collected\nSynthetic test text.",
            'effective_at' => now()->subMinute(),
            'requires_acknowledgement' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'published_at' => now(),
            'approval_reference' => 'synthetic-approval',
            'content_hash' => hash('sha256', $attributes['content']),
        ]);
    }
}

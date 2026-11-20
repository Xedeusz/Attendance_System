<?php

namespace Database\Factories;

use App\Models\PrivacyNotice;
use App\Models\PrivacyNoticeAcknowledgement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PrivacyNoticeAcknowledgement> */
class PrivacyNoticeAcknowledgementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'privacy_notice_id' => PrivacyNotice::factory()->published(),
            'acknowledged_at' => now(),
        ];
    }
}

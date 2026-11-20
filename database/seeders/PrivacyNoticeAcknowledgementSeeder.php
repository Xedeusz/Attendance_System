<?php

namespace Database\Seeders;

use App\Models\PrivacyNotice;
use App\Models\PrivacyNoticeAcknowledgement;
use Illuminate\Database\Seeder;

class PrivacyNoticeAcknowledgementSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('testing')) {
            PrivacyNoticeAcknowledgement::factory()->for(
                PrivacyNotice::factory()->published()->state(['archived_at' => now()]),
            )->create();
        }
    }
}

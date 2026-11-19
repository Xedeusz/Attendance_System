<?php

namespace Database\Seeders;

use App\Models\EssentialEmail;
use Illuminate\Database\Seeder;

class EssentialEmailSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('testing')) {
            EssentialEmail::factory()->create();
        }
    }
}

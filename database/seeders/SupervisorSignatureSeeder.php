<?php

namespace Database\Seeders;

use App\Models\SupervisorSignature;
use Illuminate\Database\Seeder;

class SupervisorSignatureSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('testing') && config('database.connections.sqlite.database') === ':memory:' && config('database.default') === 'sqlite') {
            SupervisorSignature::factory()->create();
        }
    }
}

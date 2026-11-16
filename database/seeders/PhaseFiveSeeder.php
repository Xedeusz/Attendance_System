<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PhaseFiveSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);
    }
}

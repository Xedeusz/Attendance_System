<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->insertOrIgnore([['id' => 1, 'code' => 'hr'], ['id' => 2, 'code' => 'employee']]);
    }
}

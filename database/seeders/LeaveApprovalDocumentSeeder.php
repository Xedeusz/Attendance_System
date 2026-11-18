<?php

namespace Database\Seeders;

use App\Models\LeaveApprovalDocument;
use Illuminate\Database\Seeder;

class LeaveApprovalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('testing') && config('database.connections.sqlite.database') === ':memory:' && config('database.default') === 'sqlite') {
            LeaveApprovalDocument::factory()->create();
        }
    }
}

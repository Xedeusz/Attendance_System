<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_events', function (Blueprint $table): void {
            $table->index('occurred_at');
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->index('work_date');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropIndex(['work_date']);
        });

        Schema::table('attendance_events', function (Blueprint $table): void {
            $table->dropIndex(['occurred_at']);
        });
    }
};

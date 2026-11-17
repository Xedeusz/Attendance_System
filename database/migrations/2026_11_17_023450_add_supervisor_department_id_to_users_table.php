<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('supervisor_department_id')->nullable()->constrained('departments')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('users')->whereNotNull('supervisor_department_id')->exists()) {
            throw new RuntimeException('Supervisor department links exist. Use a reviewed forward migration.');
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_department_id');
        });
    }
};

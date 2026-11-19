<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('previous_id')->nullable()->constrained('work_schedules')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->time('starts_at');
            $table->time('ends_at');
            $table->time('break_starts_at')->nullable();
            $table->time('break_ends_at')->nullable();
            $table->boolean('paid_break')->default(false);
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->string('grace_method', 24);
            $table->json('working_days');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('active')->default(true);
            $table->index(['active', 'effective_from', 'effective_until'], 'work_schedule_effective');
            $table->timestamps();
        });
        Schema::create('work_schedule_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_schedule_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->date('work_date');
            $table->string('kind', 20);
            $table->time('adjusted_end')->nullable();
            $table->string('reason', 1000);
            $table->boolean('active')->default(true);
            $table->index(['employee_id', 'work_date', 'active'], 'work_exception_employee_day');
            $table->timestamps();
        });
        Schema::create('holiday_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('source_url', 2048);
            $table->string('declaration', 255);
            $table->char('content_hash', 64)->unique();
            $table->unsignedSmallInteger('imported_count');
            $table->timestamps();
        });
        Schema::create('attendance_holidays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('holiday_import_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('name', 150);
            $table->date('holiday_date');
            $table->string('type', 32);
            $table->string('status', 16);
            $table->boolean('work_expected');
            $table->text('description')->nullable();
            $table->string('declaration', 255)->nullable();
            $table->string('proclamation_number', 100)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('scope', 16);
            $table->json('locations')->nullable();
            $table->char('identity_hash', 64)->unique();
            $table->index(['holiday_date', 'status']);
            $table->timestamps();
        });
        Schema::create('attendance_holiday_branch', function (Blueprint $table): void {
            $table->foreignId('attendance_holiday_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->primary(['attendance_holiday_id', 'branch_id'], 'holiday_branch_pk');
        });
        Schema::create('attendance_computations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_record_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('attendance_correction_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('work_schedule_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('trigger', 24);
            $table->string('reason', 1000)->nullable();
            $table->json('policy');
            $table->json('holiday_types');
            $table->json('timestamps_used');
            foreach (['expected', 'recorded', 'unpaid_break', 'net', 'late', 'undertime'] as $metric) {
                $table->unsignedInteger($metric.'_minutes')->nullable();
            }
            $table->boolean('incomplete');
            $table->timestamps();
            $table->unique(['attendance_record_id', 'version'], 'attendance_calculation_revision');
            $table->unique(['attendance_correction_request_id', 'version'], 'manual_calculation_revision');
        });
    }

    public function down(): void
    {
        foreach (['attendance_computations', 'attendance_holiday_branch', 'attendance_holidays', 'holiday_imports', 'work_schedule_exceptions', 'work_schedules'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

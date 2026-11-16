<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->enum('code', ['hr', 'employee', 'supervisor'])->change();
        });
        DB::table('roles')->insertOrIgnore(['id' => 3, 'code' => 'supervisor']);
        Schema::create('supervisor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('revoked_at', 6)->nullable();
            $table->foreignId('assigned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->index(['employee_id', 'is_active', 'effective_from'], 'supervisor_employee_dates');
            $table->timestamps();
        });
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('requires_document')->default(false);
            $table->boolean('affects_balance')->default(false);
            $table->boolean('allows_half_day')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('duration_policy', 30)->default('requested_days');
            $table->timestamps();
        });
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_supervisor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('requested_duration', 8, 2);
            $table->decimal('charged_duration', 8, 2)->nullable();
            $table->string('half_day', 10)->nullable();
            $table->text('reason');
            $table->json('type_snapshot');
            $table->string('status', 40)->index();
            $table->dateTime('submitted_at', 6);
            $table->dateTime('reviewed_at', 6)->nullable();
            $table->dateTime('finalized_at', 6)->nullable();
            $table->dateTime('cancelled_at', 6)->nullable();
            $table->index(['employee_id', 'start_date', 'end_date'], 'leave_employee_dates');
            $table->timestamps();
        });
        Schema::create('leave_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 40);
            $table->text('remarks')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('reviewed_at', 6);
            $table->unsignedBigInteger('final_request_id')->nullable()->storedAs("CASE WHEN action IN ('approved', 'rejected') THEN leave_request_id ELSE NULL END");
            $table->unique('final_request_id');
            $table->timestamps();
        });
        Schema::create('employee_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->string('policy_period', 4);
            $table->decimal('available_days', 10, 2)->default(0);
            $table->unique(['employee_id', 'leave_type_id', 'policy_period'], 'leave_balance_period');
            $table->timestamps();
        });
        Schema::create('leave_balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_leave_balance_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_request_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('authorized_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('transaction_type', 30);
            $table->decimal('amount', 10, 2);
            $table->text('reason');
            $table->timestamps();
        });
        Schema::create('leave_supporting_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->restrictOnDelete();
            $table->string('storage_path')->unique();
            $table->string('original_name', 200);
            $table->string('mime_type', 80);
            $table->unsignedInteger('file_size');
            $table->dateTime('purge_after')->nullable()->index();
            $table->dateTime('purged_at')->nullable();
            $table->timestamps();
        });
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dateTime('effective_time_in_at', 6)->nullable();
            $table->dateTime('effective_time_out_at', 6)->nullable();
            $table->unsignedInteger('correction_version')->default(0);
        });
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->unsignedBigInteger('open_employee_id')->nullable()->storedAs("CASE WHEN session_status IN ('pending', 'open') AND effective_time_out_at IS NULL THEN employee_id ELSE NULL END")->change();
        });
        Schema::create('attendance_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_record_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('correction_type', 40);
            $table->date('requested_date');
            $table->dateTime('requested_time_in_at', 6)->nullable();
            $table->dateTime('requested_time_out_at', 6)->nullable();
            $table->json('original_values');
            $table->unsignedInteger('record_revision')->default(0);
            $table->text('reason');
            $table->string('status', 20)->default('pending')->index();
            $table->char('pending_key', 64)->nullable()->unique();
            $table->dateTime('submitted_at', 6);
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('reviewed_at', 6)->nullable();
            $table->text('review_remarks')->nullable();
            $table->index(['employee_id', 'requested_date'], 'correction_employee_date');
            $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('leave_requests')->exists() || DB::table('leave_types')->exists() || DB::table('supervisor_assignments')->exists() || DB::table('employee_leave_balances')->exists() || DB::table('attendance_correction_requests')->exists() || DB::table('users')->where('role_id', 3)->exists()) {
            throw new RuntimeException('Phase 5 contains business history. Use a reviewed forward migration instead of deleting it.');
        }
        foreach (['notifications', 'attendance_correction_requests', 'leave_supporting_documents', 'leave_balance_transactions', 'employee_leave_balances', 'leave_reviews', 'leave_requests', 'leave_types', 'supervisor_assignments'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('attendance_records', fn (Blueprint $table) => $table->unsignedBigInteger('open_employee_id')->nullable()->storedAs("CASE WHEN session_status IN ('pending', 'open') THEN employee_id ELSE NULL END")->change());
        Schema::table('attendance_records', fn (Blueprint $table) => $table->dropColumn(['effective_time_in_at', 'effective_time_out_at', 'correction_version']));
        DB::table('roles')->where('id', 3)->delete();
        Schema::table('roles', fn (Blueprint $table) => $table->enum('code', ['hr', 'employee'])->change());
    }
};

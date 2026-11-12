<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedInteger('attendance_version')->default(0);
        });
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->dateTime('time_in_at', 6)->nullable();
            $table->dateTime('time_out_at', 6)->nullable();
            $table->enum('session_status', ['pending', 'open', 'completed', 'cancelled'])->default('pending');
            $table->enum('verification_status', ['location_validated', 'pending_review', 'exception_approved', 'rejected']);
            $table->enum('review_status', ['not_required', 'pending', 'approved', 'rejected'])->default('not_required');
            $table->string('classification', 30)->nullable();
            $table->unsignedBigInteger('open_employee_id')->nullable()->storedAs("CASE WHEN session_status IN ('pending', 'open') THEN employee_id ELSE NULL END");
            $table->unique('open_employee_id');
            $table->index(['employee_id', 'work_date']);
            $table->index(['branch_id', 'work_date']);
            $table->index(['review_status', 'work_date']);
            $table->timestamps();
        });
        Schema::create('attendance_capture_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('assignment_id')->constrained('employee_branch_assignments')->restrictOnDelete();
            $table->foreignId('attendance_record_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('action', ['time_in', 'time_out']);
            $table->char('token_hash', 64);
            $table->char('session_hash', 64);
            $table->unsignedInteger('attendance_version');
            $table->json('branch_snapshot');
            $table->dateTime('expires_at', 6)->index();
            $table->dateTime('consumed_at', 6)->nullable();
            $table->char('request_hash', 64)->nullable();
            $table->timestamps();
        });
        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_record_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('capture_session_id')->unique()->constrained('attendance_capture_sessions')->restrictOnDelete();
            $table->enum('event_type', ['time_in', 'time_out']);
            $table->dateTime('occurred_at', 6);
            $table->text('location')->nullable();
            $table->double('accuracy_meters')->nullable();
            $table->double('distance_meters')->nullable();
            $table->string('geofence_status', 40);
            $table->string('verification_status', 30);
            $table->enum('review_status', ['not_required', 'pending', 'approved', 'rejected']);
            $table->text('exception_reason')->nullable();
            $table->json('branch_snapshot');
            $table->dateTime('location_purged_at')->nullable();
            $table->string('effective_type', 10)->nullable()->storedAs("CASE WHEN review_status = 'rejected' THEN NULL ELSE event_type END");
            $table->unique(['attendance_record_id', 'effective_type'], 'one_effective_action_per_session');
            $table->index(['review_status', 'occurred_at']);
            $table->timestamps();
        });
        Schema::create('attendance_photo_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_event_id')->unique()->constrained()->restrictOnDelete();
            $table->string('storage_disk', 30)->default('attendance');
            $table->string('storage_path')->nullable();
            $table->string('original_format', 10);
            $table->string('mime_type', 30)->default('image/jpeg');
            $table->unsignedInteger('file_size');
            $table->unsignedInteger('image_width');
            $table->unsignedInteger('image_height');
            $table->char('file_hash', 64)->index();
            $table->dateTime('purged_at')->nullable();
            $table->timestamps();
        });
        Schema::create('attendance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_event_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('action', ['approved', 'rejected']);
            $table->text('remarks');
            $table->dateTime('reviewed_at', 6);
            $table->timestamps();
        });
        Schema::create('attendance_assistance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->enum('action', ['time_in', 'time_out']);
            $table->text('reason');
            $table->enum('status', ['pending', 'resolved', 'rejected'])->default('pending')->index();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['attendance_assistance_requests', 'attendance_reviews', 'attendance_photo_evidence', 'attendance_events', 'attendance_capture_sessions', 'attendance_records'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn('attendance_version'));
    }
};

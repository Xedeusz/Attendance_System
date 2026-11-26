<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('special_leave_eligibility_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->string('reference')->unique();
            $table->string('event_category');
            $table->date('expected_event_date')->nullable();
            $table->date('actual_event_date')->nullable();
            $table->string('status')->default('pending_hr_review');
            $table->text('additional_information')->nullable();
            $table->boolean('employee_declaration')->default(false);
            $table->timestamp('submitted_at');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('hr_remarks')->nullable();
            $table->timestamp('last_resubmitted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'submitted_at']);
            $table->index(['leave_type_id', 'status']);
            $table->index(['employee_id', 'updated_at']);
        });
        Schema::create('special_leave_eligibility_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('special_leave_eligibility_request_id');
            $table->string('storage_path')->unique();
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('document_category')->default('supporting_document');
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('special_leave_eligibility_request_id', 'special_leave_documents_request_fk')
                ->references('id')
                ->on('special_leave_eligibility_requests')
                ->cascadeOnDelete();
        });
        Schema::create('special_leave_eligibility_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('special_leave_eligibility_request_id');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->text('remarks')->nullable();
            $table->timestamp('created_at');
            $table->index(['special_leave_eligibility_request_id', 'created_at'], 'special_leave_activity_timeline_index');
            $table->foreign('special_leave_eligibility_request_id', 'special_leave_activities_request_fk')
                ->references('id')
                ->on('special_leave_eligibility_requests')
                ->cascadeOnDelete();
        });
        Schema::table('leave_entitlements', function (Blueprint $table) {
            $table->foreignId('special_leave_eligibility_request_id')->nullable()->after('leave_policy_id')->unique();
            $table->foreign('special_leave_eligibility_request_id', 'leave_entitlements_eligibility_request_fk')
                ->references('id')
                ->on('special_leave_eligibility_requests')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_entitlements', function (Blueprint $table) {
            $table->dropForeign('leave_entitlements_eligibility_request_fk');
            $table->dropUnique(['special_leave_eligibility_request_id']);
            $table->dropColumn('special_leave_eligibility_request_id');
        });
        Schema::dropIfExists('special_leave_eligibility_activities');
        Schema::dropIfExists('special_leave_eligibility_documents');
        Schema::dropIfExists('special_leave_eligibility_requests');
    }
};

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
        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->enum('allocation_basis', ['annual', 'event']);
            $table->decimal('default_days', 8, 2);
            $table->enum('day_basis', ['working_days', 'calendar_days'])->default('working_days');
            $table->decimal('carryover_limit', 8, 2)->nullable();
            $table->string('proration_rule')->default('none');
            $table->json('eligibility_rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['leave_type_id', 'effective_from']);
            $table->index(['allocation_basis', 'is_active', 'effective_from']);
        });

        Schema::create('leave_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_policy_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('source', ['annual_allocation', 'event_grant', 'opening_balance', 'manual_grant']);
            $table->string('reference');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('event_date')->nullable();
            $table->string('event_variant')->nullable();
            $table->decimal('granted_days', 8, 2);
            $table->enum('eligibility_status', ['not_required', 'pending', 'verified', 'rejected'])->default('not_required');
            $table->text('verification_notes')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'reference']);
            $table->index(['employee_id', 'period_start', 'period_end']);
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->decimal('reserved_duration', 8, 2)->nullable()->after('requested_duration');
        });

        Schema::table('leave_balance_transactions', function (Blueprint $table) {
            $table->foreignId('leave_entitlement_id')->nullable()->after('leave_request_id')->constrained()->nullOnDelete();
            $table->foreignId('reversal_of_transaction_id')->nullable()->after('leave_entitlement_id')->constrained('leave_balance_transactions')->nullOnDelete();
            $table->date('effective_on')->nullable()->after('amount');
            $table->string('idempotency_key')->nullable()->after('effective_on')->unique();
            $table->json('metadata')->nullable()->after('reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_balance_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('leave_entitlement_id');
            $table->dropConstrainedForeignId('reversal_of_transaction_id');
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['effective_on', 'idempotency_key', 'metadata']);
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn('reserved_duration');
        });

        Schema::dropIfExists('leave_entitlements');
        Schema::dropIfExists('leave_policies');
    }
};

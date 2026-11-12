<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->enum('code', ['hr', 'employee'])->unique();
        });
        DB::table('roles')->insert([['id' => 1, 'code' => 'hr'], ['id' => 2, 'code' => 'employee']]);
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('role_id')->default(2);
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
            $table->enum('account_status', ['pending_activation', 'active', 'deactivated'])->default('pending_activation')->index();
            $table->timestamp('last_login_at')->nullable();
            $table->unsignedInteger('session_version')->default(1);
        });
        // Preserve existing logins with least privilege; never promote existing users implicitly.
        DB::table('users')->update(['account_status' => 'active']);
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('branch_code', 30)->unique();
            $table->string('branch_name', 150);
            $table->string('address', 255);
            $table->string('city', 100);
            $table->string('province', 100);
            $table->string('postal_code', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('geofence_radius_meters')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
        });
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('department_code', 30)->unique();
            $table->string('department_name', 150);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('employee_number', 40)->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 20)->nullable();
            $table->string('contact_number', 30)->nullable();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('position_title', 150);
            $table->date('date_hired');
            $table->enum('employment_status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->index(['employment_status', 'last_name']);
        });
        Schema::create('employee_branch_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->dateTime('effective_from', 6);
            $table->dateTime('effective_until', 6)->nullable();
            $table->unsignedBigInteger('current_employee_id')->nullable()->storedAs('CASE WHEN effective_until IS NULL AND is_primary = 1 THEN employee_id ELSE NULL END');
            $table->unique('current_employee_id', 'one_current_primary_per_employee');
            $table->index(['branch_id', 'effective_until']);
            $table->timestamps();
        });
        Schema::create('account_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->enum('delivery_status', ['pending', 'local_preview', 'submitted', 'failed', 'not_configured'])->default('pending');
            $table->timestamps();
            $table->index(['user_id', 'used_at', 'revoked_at']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 80)->index();
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
        Schema::create('local_mail_messages', function (Blueprint $table) {
            $table->id();
            $table->char('recipient_hash', 64)->index();
            $table->text('payload');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_mail_messages');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('account_invitations');
        Schema::dropIfExists('employee_branch_assignments');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('branches');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn(['role_id', 'account_status', 'last_login_at', 'session_version']);
        });
        Schema::dropIfExists('roles');
    }
};

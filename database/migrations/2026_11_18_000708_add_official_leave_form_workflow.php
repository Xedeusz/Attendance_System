<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->text('form_snapshot')->nullable();
        });

        Schema::create('supervisor_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supervisor_user_id')->constrained('users');
            $table->string('storage_path')->unique();
            $table->unsignedInteger('version');
            $table->timestamp('registered_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['supervisor_user_id', 'version']);
        });

        Schema::create('leave_approval_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->unique()->constrained();
            $table->foreignId('approved_by_user_id')->constrained('users');
            $table->foreignId('supervisor_signature_id')->constrained();
            $table->string('document_reference')->unique();
            $table->string('storage_path')->unique();
            $table->char('sha256_hash', 64);
            $table->unsignedInteger('version');
            $table->string('template_reference');
            $table->string('approver_name');
            $table->timestamp('generated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('leave_approval_documents')->exists() || DB::table('supervisor_signatures')->exists() || DB::table('leave_requests')->whereNotNull('form_snapshot')->exists()) {
            throw new RuntimeException('Official leave history exists. Use a forward migration to preserve it.');
        }

        Schema::dropIfExists('leave_approval_documents');
        Schema::dropIfExists('supervisor_signatures');
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn('form_snapshot');
        });
    }
};

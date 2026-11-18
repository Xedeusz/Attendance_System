<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_approval_documents', function (Blueprint $table): void {
            $table->unique(['leave_request_id', 'version'], 'leave_approval_request_version_unique');
            $table->char('verification_token_hash', 64)->nullable()->unique();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users');
            $table->foreignId('superseded_by_document_id')->nullable()->constrained('leave_approval_documents', indexName: 'leave_approval_replacement_foreign');
        });

        Schema::table('leave_approval_documents', function (Blueprint $table): void {
            $table->dropUnique(['leave_request_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('leave_approval_documents')->whereNotNull('verification_token_hash')->orWhereNotNull('revoked_at')->exists()) {
            throw new RuntimeException('Leave verification history exists. Preserve it with a forward migration.');
        }

        Schema::table('leave_approval_documents', function (Blueprint $table): void {
            $table->unique('leave_request_id');
        });

        Schema::table('leave_approval_documents', function (Blueprint $table): void {
            $table->dropUnique('leave_approval_request_version_unique');
            $table->dropUnique(['verification_token_hash']);
            $table->dropConstrainedForeignId('revoked_by_user_id');
            $table->dropForeign('leave_approval_replacement_foreign');
            $table->dropColumn(['superseded_by_document_id', 'verification_token_hash', 'revoked_at']);
        });
    }
};

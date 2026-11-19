<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('essential_emails', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 90)->unique();
            $table->string('type', 24);
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('recipient_email');
            $table->foreignId('account_invitation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('leave_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('leave_review_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('leave_approval_document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_reference')->nullable();
            $table->text('auth_token')->nullable();
            $table->string('link_base', 2048);
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 16)->default('queued');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('failure_reason', 64)->nullable();
            $table->boolean('attachment_included')->default(false);
            $table->timestamp('enqueued_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'enqueued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('essential_emails');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_notices', function (Blueprint $table): void {
            $table->id();
            $table->string('version', 40)->unique();
            $table->string('title', 150);
            $table->text('content');
            $table->string('contact_email')->nullable();
            $table->boolean('requires_acknowledgement')->default(false);
            $table->timestamp('effective_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('approval_reference', 150)->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->timestamps();
            $table->index(['archived_at', 'effective_at', 'published_at']);
        });

        Schema::create('privacy_notice_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('privacy_notice_id')->constrained()->restrictOnDelete();
            $table->timestamp('acknowledged_at');
            $table->unique(['user_id', 'privacy_notice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_notice_acknowledgements');
        Schema::dropIfExists('privacy_notices');
    }
};

<?php

declare(strict_types=1);

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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Owner
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Original file information
            $table->string('original_filename', 255);
            $table->string('original_extension', 20);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('page_count')->nullable();

            // Storage
            $table->string('storage_disk', 50)->default('local');
            $table->string('storage_path', 500);
            $table->string('stored_filename', 255);
            $table->boolean('is_encrypted')->default(true);
            $table->string('encryption_key_id', 100)->nullable();

            // Integrity - SHA-256 hash
            $table->char('sha256_hash', 64);
            $table->string('hash_algorithm', 20)->default('SHA-256');
            $table->timestamp('hash_verified_at')->nullable();

            // TSA timestamp for upload
            $table->foreignId('upload_tsa_token_id')
                ->nullable()
                ->constrained('tsa_tokens')
                ->nullOnDelete();

            // Thumbnail
            $table->string('thumbnail_path', 500)->nullable();
            $table->timestamp('thumbnail_generated_at')->nullable();

            // PDF metadata extracted
            $table->json('pdf_metadata')->nullable();
            $table->string('pdf_version', 20)->nullable();
            $table->boolean('is_pdf_a')->default(false);
            $table->boolean('has_signatures')->default(false);
            $table->boolean('has_encryption')->default(false);
            $table->boolean('has_javascript')->default(false);

            // Status
            $table->enum('status', ['pending', 'processing', 'ready', 'error', 'deleted'])
                ->default('pending');
            $table->text('error_message')->nullable();

            // Timestamps and soft delete
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('tenant_id');
            $table->index('user_id');
            $table->index('sha256_hash');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};

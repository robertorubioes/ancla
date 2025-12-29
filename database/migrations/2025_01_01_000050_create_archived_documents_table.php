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
        Schema::create('archived_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Reference to the original document
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();

            // Archive tier management (hot -> cold -> archive)
            $table->enum('archive_tier', ['hot', 'cold', 'archive'])->default('hot');

            // Storage paths
            $table->string('original_storage_path', 500);
            $table->string('archive_storage_path', 500)->nullable();
            $table->string('storage_disk', 50)->default('local');
            $table->string('storage_bucket', 100)->nullable();

            // Retention policy reference
            $table->foreignId('retention_policy_id')->nullable()->constrained()->nullOnDelete();

            // Content integrity
            $table->string('content_hash', 64);
            $table->string('hash_algorithm', 20)->default('SHA-256');
            $table->string('archive_hash', 64)->nullable();

            // Format tracking
            $table->string('format_version', 20)->default('1.0');
            $table->string('current_format', 20)->default('PDF');
            $table->string('pdfa_version', 20)->nullable();
            $table->timestamp('format_migrated_at')->nullable();

            // Key dates
            $table->timestamp('archived_at');
            $table->timestamp('next_reseal_at')->nullable();
            $table->timestamp('retention_expires_at');
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();

            // TSA chain reference
            $table->unsignedBigInteger('initial_tsa_token_id')->nullable();
            $table->unsignedBigInteger('current_tsa_chain_id')->nullable();
            $table->unsignedInteger('reseal_count')->default(0);

            // Archive status
            $table->enum('archive_status', ['pending', 'active', 'migrating', 'expired', 'deleted'])
                ->default('pending');
            $table->text('status_reason')->nullable();

            // Metadata (JSON)
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('tenant_id');
            $table->index('document_id');
            $table->index('archive_tier');
            $table->index('archive_status');
            $table->index('retention_expires_at');
            $table->index('next_reseal_at');
            $table->index('content_hash');

            // Foreign key for TSA token (manual because of nullable)
            $table->foreign('initial_tsa_token_id')
                ->references('id')
                ->on('tsa_tokens')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archived_documents');
    }
};

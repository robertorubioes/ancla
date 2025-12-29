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
        Schema::create('tsa_chains', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Reference to the document (can be linked via archived_document or document)
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();

            // Type of chain (document, evidence_package, audit_trail)
            $table->enum('chain_type', ['document', 'evidence_package', 'audit_trail'])
                ->default('document');

            // Hash being preserved
            $table->string('preserved_hash', 64);
            $table->string('hash_algorithm', 20)->default('SHA-256');

            // Chain status
            $table->enum('status', ['active', 'resealing', 'completed', 'broken', 'expired', 'migrated'])
                ->default('active');

            // Initial TSA token (sequence 0)
            $table->foreignId('initial_tsa_token_id')
                ->constrained('tsa_tokens')
                ->cascadeOnDelete();

            // First and last seal timestamps
            $table->timestamp('first_seal_at');
            $table->timestamp('last_seal_at');

            // Seal count and scheduling
            $table->unsignedInteger('seal_count')->default(1);
            $table->timestamp('next_seal_due_at');
            $table->unsignedInteger('reseal_interval_days')->default(365);

            // Last reseal TSA token reference
            $table->unsignedBigInteger('last_reseal_tsa_id')->nullable();

            // Verification tracking
            $table->timestamp('last_verified_at')->nullable();
            $table->enum('verification_status', ['pending', 'valid', 'invalid'])
                ->default('pending');

            $table->timestamps();

            // Indexes
            $table->index('tenant_id');
            $table->index('document_id');
            $table->index('status');
            $table->index('next_seal_due_at');
            $table->index('preserved_hash');
            $table->index(['chain_type', 'status']);

            // Foreign key for last reseal TSA
            $table->foreign('last_reseal_tsa_id')
                ->references('id')
                ->on('tsa_tokens')
                ->nullOnDelete();
        });

        // Add the foreign key back-reference to archived_documents now that tsa_chains exists
        Schema::table('archived_documents', function (Blueprint $table) {
            $table->foreign('current_tsa_chain_id')
                ->references('id')
                ->on('tsa_chains')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('archived_documents', function (Blueprint $table) {
            $table->dropForeign(['current_tsa_chain_id']);
        });

        Schema::dropIfExists('tsa_chains');
    }
};

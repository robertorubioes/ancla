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
        Schema::create('tsa_chain_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Reference to the chain
            $table->foreignId('tsa_chain_id')
                ->constrained('tsa_chains')
                ->cascadeOnDelete();

            // Position in the chain (0 = original, 1+ = reseals)
            $table->unsignedInteger('sequence_number');

            // TSA token for this entry
            $table->foreignId('tsa_token_id')
                ->constrained('tsa_tokens')
                ->cascadeOnDelete();

            // Hash chain integrity
            $table->string('previous_entry_hash', 64)->nullable(); // NULL for sequence 0
            $table->string('cumulative_hash', 64); // Hash of all previous entries + current
            $table->string('sealed_hash', 64); // The hash that was sealed by TSA

            // Reason for this re-seal
            $table->enum('reseal_reason', ['initial', 'scheduled', 'algorithm_upgrade', 'certificate_expiry', 'manual'])
                ->default('scheduled');

            // TSA provider details (denormalized for audit)
            $table->string('tsa_provider', 100);
            $table->string('algorithm_used', 50);
            $table->timestamp('timestamp_value', 6);

            // Certificate expiry tracking
            $table->timestamp('sealed_at');
            $table->timestamp('expires_at')->nullable();

            // Previous entry reference for chain integrity
            $table->unsignedBigInteger('previous_entry_id')->nullable();

            // Metadata (JSON)
            $table->json('metadata')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index('tsa_chain_id');
            $table->index(['tsa_chain_id', 'sequence_number']);
            $table->index('timestamp_value');
            $table->index('expires_at');

            // Unique constraint: one entry per sequence number per chain
            $table->unique(['tsa_chain_id', 'sequence_number'], 'uk_chain_sequence');

            // Foreign key for previous entry (self-referential)
            $table->foreign('previous_entry_id')
                ->references('id')
                ->on('tsa_chain_entries')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tsa_chain_entries');
    }
};

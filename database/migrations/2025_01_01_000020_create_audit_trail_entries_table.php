<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the audit_trail_entries table for immutable event logging.
 *
 * Each entry contains a hash of the previous entry creating a blockchain-like
 * structure that makes tampering detectable. Critical events also include
 * a TSA token for qualified timestamps (eIDAS compliant).
 *
 * @see ADR-005 in docs/architecture/decisions.md
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_trail_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Context of the audited event (polymorphic relation)
            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id');

            // Event type and category
            $table->string('event_type', 50);
            $table->enum('event_category', ['document', 'signature', 'access', 'system']);

            // Event data (JSON payload with specific event details)
            $table->json('payload')->nullable();

            // Actor who performed the action
            $table->enum('actor_type', ['user', 'signer', 'system', 'api']);
            $table->unsignedBigInteger('actor_id')->nullable();

            // Request context
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Chained hash (blockchain-like immutability)
            $table->char('hash', 64)->comment('SHA-256 hash of this entry');
            $table->char('previous_hash', 64)->nullable()->comment('Hash of the previous entry in the chain');
            $table->unsignedBigInteger('sequence')->comment('Sequence number within the tenant');

            // TSA token reference (nullable, only for critical events)
            // Foreign key constraint added after tsa_tokens table exists
            $table->unsignedBigInteger('tsa_token_id')->nullable();

            // Created timestamp with microsecond precision (no updated_at - entries are immutable)
            $table->timestamp('created_at', 6)->useCurrent();

            // Indexes for efficient querying
            $table->index('tenant_id');
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('event_type');
            $table->index(['actor_type', 'actor_id']);
            $table->index(['tenant_id', 'sequence']);
            $table->index('hash');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_trail_entries');
    }
};

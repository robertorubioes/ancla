<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the evidence_packages table for legal evidence bundles.
 *
 * Evidence packages contain all proof materials for a signed document:
 * - Original document with hash
 * - Audit trail entries
 * - TSA tokens
 * - Signature data
 *
 * These packages are self-contained and can be verified independently.
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
        Schema::create('evidence_packages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Polymorphic relation to the packaged item (Document, SignatureProcess, etc.)
            $table->string('packagable_type', 100);
            $table->unsignedBigInteger('packagable_id');

            // Document integrity
            $table->char('document_hash', 64)->comment('SHA-256 hash of the document');

            // Audit trail integrity
            $table->char('audit_trail_hash', 64)->comment('Hash of the complete audit trail');

            // TSA token for the entire package
            $table->foreignId('tsa_token_id')->nullable()->constrained('tsa_tokens')->nullOnDelete();

            // Package status
            $table->enum('status', ['pending', 'generating', 'ready', 'failed', 'expired'])
                ->default('pending');

            // Generation timestamp
            $table->timestamp('generated_at')->nullable();

            // Standard timestamps
            $table->timestamps();

            // Indexes
            $table->index('tenant_id');
            $table->index(['packagable_type', 'packagable_id']);
            $table->index('status');
            $table->index('document_hash');
            $table->index('generated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evidence_packages');
    }
};

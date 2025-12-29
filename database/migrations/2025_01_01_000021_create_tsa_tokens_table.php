<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the tsa_tokens table for RFC 3161 timestamp storage.
 *
 * TSA (Time Stamping Authority) tokens provide qualified timestamps that
 * prove when a document or event occurred. These are essential for
 * eIDAS compliance and legal validity of electronic signatures.
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
        Schema::create('tsa_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Hash that was timestamped
            $table->string('hash_algorithm', 20)->default('SHA-256');
            $table->char('data_hash', 64)->comment('SHA-256 hash that was sent to TSA');

            // TSA token data (base64 encoded binary)
            $table->longText('token')->comment('RFC 3161 timestamp token (base64 encoded)');

            // Provider information
            $table->enum('provider', ['firmaprofesional', 'digicert', 'sectigo', 'mock'])
                ->default('firmaprofesional');

            // Token status
            $table->enum('status', ['pending', 'valid', 'invalid', 'expired'])
                ->default('pending');

            // Timestamps from TSA
            $table->timestamp('issued_at', 6)->nullable()
                ->comment('Timestamp certified by TSA');
            $table->timestamp('verified_at')->nullable()
                ->comment('When we last verified the token');

            // Standard timestamps
            $table->timestamps();

            // Indexes
            $table->index('tenant_id');
            $table->index('data_hash');
            $table->index('provider');
            $table->index('status');
            $table->index('issued_at');
        });

        // Add foreign key constraint to audit_trail_entries now that tsa_tokens exists
        Schema::table('audit_trail_entries', function (Blueprint $table) {
            $table->foreign('tsa_token_id')
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
        // Remove foreign key first
        Schema::table('audit_trail_entries', function (Blueprint $table) {
            $table->dropForeign(['tsa_token_id']);
        });

        Schema::dropIfExists('tsa_tokens');
    }
};

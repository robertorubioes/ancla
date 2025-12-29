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
        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Reference to document
            $table->foreignId('document_id')
                ->constrained()
                ->cascadeOnDelete();

            // Verification codes
            $table->string('verification_code', 20)->unique();  // Full code: XXXX-XXXX-XXXX (12 chars + 2 dashes)
            $table->string('short_code', 10)->index();          // Short code for QR: 6 chars

            // QR Code storage
            $table->string('qr_code_path', 500)->nullable();

            // Expiration (nullable for non-expiring codes)
            $table->timestamp('expires_at')->nullable();

            // Statistics
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index('verification_code');
            $table->index('document_id');
            $table->index(['expires_at', 'verification_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_codes');
    }
};

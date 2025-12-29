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
        Schema::create('verification_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Reference to verification code
            $table->foreignId('verification_code_id')
                ->constrained('verification_codes')
                ->cascadeOnDelete();

            // Requester information
            $table->string('ip_address', 45);  // IPv6 compatible
            $table->text('user_agent')->nullable();

            // Verification result
            $table->enum('result', ['success', 'invalid_code', 'expired', 'document_not_found'])
                ->default('success');

            // Confidence level from verification
            $table->enum('confidence_level', ['high', 'medium', 'low'])->nullable();

            // Detailed verification results
            $table->json('details')->nullable();

            // Timestamp only (no updated_at needed for logs)
            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index('verification_code_id');
            $table->index('ip_address');
            $table->index('result');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_logs');
    }
};

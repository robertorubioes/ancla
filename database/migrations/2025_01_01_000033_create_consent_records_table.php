<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Contexto
            $table->string('signable_type', 100);
            $table->unsignedBigInteger('signable_id');
            $table->unsignedBigInteger('signer_id')->nullable();
            $table->string('signer_email');

            // Tipo de consentimiento
            $table->enum('consent_type', ['signature', 'terms', 'privacy', 'biometric', 'communication']);
            $table->string('consent_version', 20);

            // Texto legal
            $table->char('legal_text_hash', 64);
            $table->mediumText('legal_text_content');
            $table->string('legal_text_language', 10)->default('es');

            // Acción
            $table->enum('action', ['accepted', 'rejected', 'revoked']);
            $table->timestamp('action_timestamp', 6);

            // Screenshot
            $table->string('screenshot_path', 500)->nullable();
            $table->char('screenshot_hash', 64)->nullable();
            $table->timestamp('screenshot_captured_at', 6)->nullable();

            // Contexto UI
            $table->string('ui_element_id', 100)->nullable();
            $table->unsignedInteger('ui_visible_duration_ms')->nullable();
            $table->boolean('scroll_to_bottom')->default(false);

            // Verificación
            $table->char('verification_hash', 64);
            $table->foreignId('tsa_token_id')->nullable()->constrained('tsa_tokens');

            $table->timestamp('created_at')->useCurrent();

            // Índices
            $table->index('tenant_id');
            $table->index(['signable_type', 'signable_id']);
            $table->index('signer_email');
            $table->index('consent_type');
            $table->index('verification_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
    }
};

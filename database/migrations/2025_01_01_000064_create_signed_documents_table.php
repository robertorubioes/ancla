<?php

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
        Schema::create('signed_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Referencia al proceso y firmante
            $table->foreignId('signing_process_id')->constrained()->cascadeOnDelete();
            $table->foreignId('signer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('original_document_id')->constrained('documents');

            // Archivo firmado
            $table->string('storage_disk', 50)->default('local');
            $table->string('signed_path', 500);
            $table->string('signed_name', 255);
            $table->unsignedBigInteger('file_size');

            // Integridad
            $table->char('content_hash', 64); // SHA-256 del PDF firmado
            $table->char('original_hash', 64); // SHA-256 del PDF original
            $table->string('hash_algorithm', 20)->default('SHA-256');

            // Firma digital (signature data está en signer.signature_data)
            $table->text('pkcs7_signature'); // PKCS#7 hex-encoded
            $table->string('certificate_subject', 500);
            $table->string('certificate_issuer', 500);
            $table->string('certificate_serial', 100);
            $table->char('certificate_fingerprint', 64); // SHA-256 del cert

            // PAdES metadata
            $table->string('pades_level', 20); // 'B-B', 'B-LT', 'B-LTA'
            $table->boolean('has_tsa_token')->default(false);
            $table->foreignId('tsa_token_id')->nullable()->constrained();
            $table->boolean('has_validation_data')->default(false);

            // Signature appearance
            $table->json('signature_position'); // {page, x, y, width, height}
            $table->boolean('signature_visible')->default(true);
            $table->json('signature_appearance')->nullable(); // Layout config

            // Embedded metadata
            $table->json('embedded_metadata'); // ANCLA custom fields
            $table->foreignId('verification_code_id')->nullable()->constrained();
            $table->boolean('qr_code_embedded')->default(true);

            // Evidence package
            $table->foreignId('evidence_package_id')->constrained();

            // Validación
            $table->boolean('adobe_validated')->nullable(); // NULL = no validado aún
            $table->timestamp('adobe_validation_date')->nullable();
            $table->json('validation_errors')->nullable();

            // Estado
            $table->enum('status', ['signing', 'signed', 'error'])->default('signing');
            $table->text('error_message')->nullable();

            // Timestamps
            $table->timestamp('signed_at');
            $table->timestamps();

            // Índices
            $table->index('tenant_id', 'idx_signed_tenant');
            $table->index('signing_process_id', 'idx_signed_process');
            $table->index('signer_id', 'idx_signed_signer');
            $table->index('content_hash', 'idx_signed_hash');
            $table->index('status', 'idx_signed_status');
            $table->index('signed_at', 'idx_signed_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signed_documents');
    }
};

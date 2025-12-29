<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_dossiers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Contexto
            $table->string('signable_type', 100);
            $table->unsignedBigInteger('signable_id');

            // Tipo
            $table->enum('dossier_type', ['audit_trail', 'full_evidence', 'legal_proof', 'executive_summary']);

            // Archivo PDF
            $table->string('file_path', 500);
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->char('file_hash', 64);
            $table->unsignedInteger('page_count');

            // Contenido incluido
            $table->boolean('includes_document')->default(true);
            $table->boolean('includes_audit_trail')->default(true);
            $table->boolean('includes_device_info')->default(true);
            $table->boolean('includes_geolocation')->default(true);
            $table->boolean('includes_ip_info')->default(true);
            $table->boolean('includes_consents')->default(true);
            $table->boolean('includes_tsa_tokens')->default(true);

            // Firma del dossier
            $table->text('platform_signature')->nullable();
            $table->string('signature_algorithm', 50)->nullable();
            $table->timestamp('signed_at', 6)->nullable();

            // TSA
            $table->foreignId('tsa_token_id')->nullable()->constrained('tsa_tokens');

            // Verificación
            $table->string('verification_code', 20)->unique();
            $table->string('verification_url', 500)->nullable();
            $table->string('verification_qr_path', 500)->nullable();

            // Contadores
            $table->unsignedInteger('audit_entries_count')->default(0);
            $table->unsignedInteger('devices_count')->default(0);
            $table->unsignedInteger('geolocations_count')->default(0);
            $table->unsignedInteger('consents_count')->default(0);

            // Generación
            $table->foreignId('generated_by')->nullable()->constrained('users');
            $table->timestamp('generated_at')->useCurrent();

            // Descargas
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('last_downloaded_at')->nullable();

            // Índices
            $table->index('tenant_id');
            $table->index(['signable_type', 'signable_id']);
            $table->index('verification_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_dossiers');
    }
};

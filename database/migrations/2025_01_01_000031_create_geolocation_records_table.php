<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geolocation_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Contexto
            $table->string('signable_type', 100);
            $table->unsignedBigInteger('signable_id');
            $table->unsignedBigInteger('signer_id')->nullable();
            $table->string('signer_email')->nullable();

            // Tipo de captura
            $table->enum('capture_method', ['gps', 'ip', 'refused', 'unavailable']);
            $table->enum('permission_status', ['granted', 'denied', 'prompt', 'unavailable']);

            // Datos GPS
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('accuracy_meters', 10, 2)->nullable();
            $table->decimal('altitude_meters', 10, 2)->nullable();

            // Datos IP geolocation
            $table->decimal('ip_latitude', 10, 8)->nullable();
            $table->decimal('ip_longitude', 11, 8)->nullable();
            $table->string('ip_city', 100)->nullable();
            $table->string('ip_region', 100)->nullable();
            $table->char('ip_country', 2)->nullable();
            $table->string('ip_country_name', 100)->nullable();
            $table->string('ip_timezone', 100)->nullable();
            $table->string('ip_isp')->nullable();

            // Dirección formateada
            $table->text('formatted_address')->nullable();

            // Metadata
            $table->json('raw_gps_data')->nullable();
            $table->json('raw_ip_data')->nullable();
            $table->timestamp('captured_at', 6)->useCurrent();

            // Índices
            $table->index('tenant_id');
            $table->index(['signable_type', 'signable_id']);
            $table->index('capture_method');
            $table->index('ip_country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geolocation_records');
    }
};

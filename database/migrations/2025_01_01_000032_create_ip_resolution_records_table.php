<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ip_resolution_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Contexto
            $table->string('signable_type', 100);
            $table->unsignedBigInteger('signable_id');
            $table->unsignedBigInteger('signer_id')->nullable();
            $table->string('signer_email')->nullable();

            // IP
            $table->string('ip_address', 45);
            $table->unsignedTinyInteger('ip_version');

            // DNS inversa
            $table->string('reverse_dns')->nullable();
            $table->boolean('reverse_dns_verified')->default(false);

            // Red
            $table->string('asn', 20)->nullable();
            $table->string('asn_name')->nullable();
            $table->string('isp')->nullable();
            $table->string('organization')->nullable();

            // Detección proxy/VPN
            $table->boolean('is_proxy')->default(false);
            $table->boolean('is_vpn')->default(false);
            $table->boolean('is_tor')->default(false);
            $table->boolean('is_datacenter')->default(false);
            $table->string('proxy_type', 50)->nullable();
            $table->unsignedTinyInteger('threat_score')->nullable();

            // Headers
            $table->text('x_forwarded_for')->nullable();
            $table->string('x_real_ip', 45)->nullable();

            // Metadata
            $table->json('raw_data')->nullable();
            $table->timestamp('checked_at', 6)->useCurrent();

            // Índices
            $table->index('tenant_id');
            $table->index(['signable_type', 'signable_id']);
            $table->index('ip_address');
            $table->index('is_vpn');
            $table->index('is_proxy');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_resolution_records');
    }
};

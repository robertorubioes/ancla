<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Contexto de captura
            $table->string('signable_type', 100);
            $table->unsignedBigInteger('signable_id');
            $table->unsignedBigInteger('signer_id')->nullable();
            $table->string('signer_email')->nullable();

            // User Agent parsed
            $table->text('user_agent_raw');
            $table->string('browser_name', 100)->nullable();
            $table->string('browser_version', 50)->nullable();
            $table->string('os_name', 100)->nullable();
            $table->string('os_version', 50)->nullable();
            $table->enum('device_type', ['desktop', 'mobile', 'tablet', 'unknown'])->default('unknown');

            // Screen info
            $table->unsignedInteger('screen_width')->nullable();
            $table->unsignedInteger('screen_height')->nullable();
            $table->unsignedSmallInteger('color_depth')->nullable();
            $table->decimal('pixel_ratio', 4, 2)->nullable();

            // Environment
            $table->string('timezone', 100)->nullable();
            $table->smallInteger('timezone_offset')->nullable();
            $table->string('language', 20)->nullable();
            $table->json('languages')->nullable();
            $table->string('platform', 100)->nullable();

            // Hardware hints
            $table->unsignedTinyInteger('hardware_concurrency')->nullable();
            $table->decimal('device_memory', 5, 2)->nullable();
            $table->boolean('touch_support')->default(false);
            $table->unsignedTinyInteger('touch_points')->nullable();

            // Graphics
            $table->string('webgl_vendor')->nullable();
            $table->string('webgl_renderer')->nullable();

            // Advanced fingerprints
            $table->char('canvas_hash', 64)->nullable();
            $table->char('audio_hash', 64)->nullable();
            $table->char('fonts_hash', 64)->nullable();

            // Final fingerprint
            $table->char('fingerprint_hash', 64);
            $table->string('fingerprint_version', 10)->default('v1');

            // Metadata
            $table->json('raw_data')->nullable();
            $table->timestamp('captured_at', 6)->useCurrent();

            // Índices
            $table->index('tenant_id');
            $table->index(['signable_type', 'signable_id']);
            $table->index('fingerprint_hash');
            $table->index('signer_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_fingerprints');
    }
};

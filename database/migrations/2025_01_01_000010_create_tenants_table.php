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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('domain')->nullable()->unique();

            // Branding
            $table->string('logo_path', 500)->nullable();
            $table->string('primary_color', 7)->default('#3B82F6');
            $table->string('secondary_color', 7)->default('#1E40AF');

            // Estado
            $table->enum('status', ['active', 'suspended', 'trial', 'cancelled'])->default('trial');
            $table->enum('plan', ['free', 'starter', 'professional', 'enterprise'])->default('starter');
            $table->timestamp('trial_ends_at')->nullable();

            // Config flexible
            $table->json('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

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
        Schema::create('retention_policies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Tenant scope (NULL = global policy)
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();

            // Policy identification
            $table->string('name', 100);
            $table->text('description')->nullable();

            // Document type targeting (NULL = all types)
            $table->string('document_type', 50)->nullable();

            // Retention period
            $table->unsignedInteger('retention_years')->default(5);
            $table->unsignedInteger('retention_days')->default(0);

            // Tiering schedule (days after creation)
            $table->unsignedInteger('archive_after_days')->default(365); // Move to cold after 1 year
            $table->unsignedInteger('deep_archive_after_days')->nullable(); // Move to deep archive

            // TSA re-sealing configuration
            $table->unsignedInteger('reseal_interval_days')->default(365);
            $table->unsignedInteger('reseal_before_expiry_days')->default(90);

            // Actions on expiry
            $table->boolean('auto_delete_after_expiry')->default(false);
            $table->enum('on_expiry_action', ['archive', 'delete', 'notify', 'extend'])
                ->default('notify');

            // Format preservation
            $table->boolean('require_pdfa_conversion')->default(true);
            $table->string('target_pdfa_version', 20)->default('PDF/A-3b');

            // Policy status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('priority')->default(100); // Lower = higher priority

            $table->timestamps();

            // Indexes
            $table->index('tenant_id');
            $table->index('document_type');
            $table->index('is_active');
            $table->index('is_default');
            $table->index('priority');

            // Unique constraint: only one default policy per tenant
            $table->unique(['tenant_id', 'is_default'], 'uk_tenant_default_policy');
        });

        // Seed default global retention policy
        \DB::table('retention_policies')->insert([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'tenant_id' => null,
            'name' => 'Default eIDAS Retention Policy',
            'description' => 'Default retention policy complying with eIDAS minimum 5-year requirement',
            'document_type' => null,
            'retention_years' => 5,
            'retention_days' => 0,
            'archive_after_days' => 365,
            'deep_archive_after_days' => 3650, // 10 years
            'reseal_interval_days' => 365,
            'reseal_before_expiry_days' => 90,
            'auto_delete_after_expiry' => false,
            'on_expiry_action' => 'notify',
            'require_pdfa_conversion' => true,
            'target_pdfa_version' => 'PDF/A-3b',
            'is_active' => true,
            'is_default' => true,
            'priority' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retention_policies');
    }
};

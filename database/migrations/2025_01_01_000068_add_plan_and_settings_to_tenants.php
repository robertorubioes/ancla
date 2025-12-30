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
        Schema::table('tenants', function (Blueprint $table) {
            // Subdomain for multi-tenant access
            $table->string('subdomain', 50)->nullable()->unique()->after('slug');

            // Quotas and limits
            $table->integer('max_users')->nullable()->after('plan');
            $table->integer('max_documents_per_month')->nullable()->after('max_users');

            // Suspension tracking
            $table->timestamp('suspended_at')->nullable()->after('trial_ends_at');
            $table->text('suspended_reason')->nullable()->after('suspended_at');

            // Admin notes for internal use
            $table->text('admin_notes')->nullable()->after('suspended_reason');

            // Additional indices for performance
            $table->index('subdomain');
            $table->index('plan');
            $table->index('trial_ends_at');
            $table->index('suspended_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['subdomain']);
            $table->dropIndex(['plan']);
            $table->dropIndex(['trial_ends_at']);
            $table->dropIndex(['suspended_at']);

            $table->dropColumn([
                'subdomain',
                'max_users',
                'max_documents_per_month',
                'suspended_at',
                'suspended_reason',
                'admin_notes',
            ]);
        });
    }
};

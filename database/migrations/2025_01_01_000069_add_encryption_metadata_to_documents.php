<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds encryption metadata to documents and signed_documents tables.
     *
     * These fields track:
     * - Whether content is encrypted
     * - When encryption occurred
     * - Key version (for future key rotation support)
     */
    public function up(): void
    {
        // Add encryption metadata to documents table
        // Note: is_encrypted column already exists from create_documents_table migration
        Schema::table('documents', function (Blueprint $table) {
            // Only add NEW columns (is_encrypted already exists at line 35 of 000040 migration)
            $table->timestamp('encrypted_at')->nullable()->after('status');
            $table->string('encryption_key_version', 50)->nullable()->default('v1')->after('encrypted_at');

            // Add index conditionally to avoid duplicate
            if (! Schema::hasIndex('documents', 'documents_is_encrypted_index')) {
                $table->index('is_encrypted');
            }
        });

        // Add encryption metadata to signed_documents table
        // This table doesn't have these columns yet, so add all three
        Schema::table('signed_documents', function (Blueprint $table) {
            $table->boolean('is_encrypted')->default(false)->after('status');
            $table->timestamp('encrypted_at')->nullable()->after('is_encrypted');
            $table->string('encryption_key_version', 50)->default('v1')->after('encrypted_at');

            // Index for finding unencrypted documents
            $table->index('is_encrypted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Only drop the columns we added (not is_encrypted - it existed before)
            // Drop index conditionally
            if (Schema::hasIndex('documents', 'documents_is_encrypted_index')) {
                $table->dropIndex(['is_encrypted']);
            }
            $table->dropColumn(['encrypted_at', 'encryption_key_version']);
        });

        Schema::table('signed_documents', function (Blueprint $table) {
            $table->dropIndex(['is_encrypted']);
            $table->dropColumn(['is_encrypted', 'encrypted_at', 'encryption_key_version']);
        });
    }
};

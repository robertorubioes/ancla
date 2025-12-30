<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add final document fields to signing_processes table.
     * This supports E5-001: Generate final signed document with all signatures.
     */
    public function up(): void
    {
        Schema::table('signing_processes', function (Blueprint $table) {
            // Final document storage path
            $table->string('final_document_path')->nullable()->after('completed_at');

            // Final document filename
            $table->string('final_document_name')->nullable()->after('final_document_path');

            // Final document integrity hash (SHA-256)
            $table->string('final_document_hash', 64)->nullable()->after('final_document_name');

            // Size in bytes
            $table->unsignedBigInteger('final_document_size')->nullable()->after('final_document_hash');

            // Timestamp when final document was generated
            $table->timestamp('final_document_generated_at')->nullable()->after('final_document_size');

            // Number of pages in final document (includes certification page)
            $table->unsignedInteger('final_document_pages')->nullable()->after('final_document_generated_at');

            // Index for queries by final document existence
            $table->index('final_document_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signing_processes', function (Blueprint $table) {
            $table->dropIndex(['final_document_path']);
            $table->dropColumn([
                'final_document_path',
                'final_document_name',
                'final_document_hash',
                'final_document_size',
                'final_document_generated_at',
                'final_document_pages',
            ]);
        });
    }
};

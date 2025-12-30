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
        Schema::create('signing_processes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('created_by');

            // Process configuration
            $table->enum('status', [
                'draft',
                'sent',
                'in_progress',
                'completed',
                'expired',
                'cancelled',
            ])->default('draft');

            $table->enum('signature_order', ['sequential', 'parallel'])->default('parallel');
            $table->text('custom_message')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('tenant_id');
            $table->index('document_id');
            $table->index('created_by');
            $table->index('status');
            $table->index('created_at');
            $table->index('deadline_at');
            $table->index(['tenant_id', 'status']);

            // Foreign keys
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->foreign('document_id')
                ->references('id')
                ->on('documents')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signing_processes');
    }
};

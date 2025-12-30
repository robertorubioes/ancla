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
        Schema::create('signers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('signing_process_id');

            // Signer information
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();

            // Signing order
            $table->integer('order')->default(0);

            // Status tracking
            $table->enum('status', [
                'pending',
                'sent',
                'viewed',
                'signed',
                'rejected',
            ])->default('pending');

            // Access token for signing
            $table->string('token', 64)->unique();

            // Timestamps
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            // Rejection reason
            $table->text('rejection_reason')->nullable();

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('signing_process_id');
            $table->index('status');
            $table->index('email');
            $table->index('order');
            $table->index(['signing_process_id', 'status']);
            $table->index(['signing_process_id', 'order']);

            // Foreign keys
            $table->foreign('signing_process_id')
                ->references('id')
                ->on('signing_processes')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signers');
    }
};

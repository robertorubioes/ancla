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
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->string('email');
            $table->string('name');
            $table->enum('role', ['admin', 'operator', 'viewer'])
                ->default('viewer');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('invited_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->unsignedTinyInteger('resend_count')->default(0);
            $table->timestamp('last_resent_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'email']);
            $table->index(['token', 'expires_at']);
            $table->index('accepted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_invitations');
    }
};

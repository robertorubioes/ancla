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
        Schema::table('signers', function (Blueprint $table) {
            $table->timestamp('copy_sent_at')->nullable()->after('signed_at');
            $table->string('download_token', 64)->nullable()->unique()->after('copy_sent_at');
            $table->timestamp('download_expires_at')->nullable()->after('download_token');
            $table->timestamp('downloaded_at')->nullable()->after('download_expires_at');
            $table->integer('download_count')->default(0)->after('downloaded_at');

            $table->index('download_token');
            $table->index('download_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signers', function (Blueprint $table) {
            $table->dropIndex(['download_token']);
            $table->dropIndex(['download_expires_at']);

            $table->dropColumn([
                'copy_sent_at',
                'download_token',
                'download_expires_at',
                'downloaded_at',
                'download_count',
            ]);
        });
    }
};

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
        Schema::table('signing_processes', function (Blueprint $table) {
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('completed_at');
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');

            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');
            $table->index('cancelled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signing_processes', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by']);
            $table->dropIndex(['cancelled_at']);

            $table->dropColumn([
                'cancelled_by',
                'cancellation_reason',
                'cancelled_at',
            ]);
        });
    }
};

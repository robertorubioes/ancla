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
            $table->string('signature_type')->nullable()->after('status');
            $table->text('signature_data')->nullable()->after('signature_type');
            $table->foreignId('evidence_package_id')->nullable()->after('signature_data')->constrained()->onDelete('set null');
            $table->json('signature_metadata')->nullable()->after('evidence_package_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signers', function (Blueprint $table) {
            $table->dropForeign(['evidence_package_id']);
            $table->dropColumn([
                'signature_type',
                'signature_data',
                'evidence_package_id',
                'signature_metadata',
            ]);
        });
    }
};

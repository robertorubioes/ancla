<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas de documentos.
 *
 * Una plantilla es un PDF inmutable mas un esquema de campos posicionados.
 * Publicar cambios crea una version nueva en lugar de mutar la existente: un
 * proceso de firma apunta siempre a la version con la que se genero, para
 * poder demostrar anos despues que produjo el documento firmado.
 *
 * @see docs/architecture/adr-011-plantillas-y-api-de-cumplimentacion.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'active', 'archived'])
                ->default('draft');
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('document_template_versions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignId('document_template_id')
                ->constrained('document_templates')
                ->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('document_id')
                ->comment('PDF base sobre el que se estampan los valores')
                ->constrained('documents')
                ->restrictOnDelete();
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['document_template_id', 'version'], 'tpl_versions_template_version_unique');
            $table->index('tenant_id');
            $table->index('published_at');
        });

        // La version publicada vigente. Se anade despues de crear la tabla de
        // versiones para poder referenciarla.
        Schema::table('document_templates', function (Blueprint $table) {
            $table->foreignId('current_version_id')
                ->nullable()
                ->after('status')
                ->constrained('document_template_versions')
                ->nullOnDelete();
        });

        Schema::create('document_template_fields', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignId('document_template_version_id')
                ->constrained('document_template_versions')
                ->cascadeOnDelete();

            // Identidad del campo
            $table->string('key', 64)->comment('Clave en el formulario y en el JSON de la API');
            $table->string('label');
            $table->string('help_text')->nullable();
            $table->enum('type', [
                'text',
                'textarea',
                'number',
                'date',
                'select',
                'checkbox',
                'signer_name',
                'signer_email',
                'today',
            ])->default('text');
            $table->boolean('required')->default(true);
            $table->text('default_value')->nullable();
            $table->json('options')->nullable()->comment('Opciones de un select');
            $table->json('validation')->nullable()->comment('Reglas extra: min, max, regex');

            // Posicion sobre el PDF, en milimetros desde arriba a la izquierda
            $table->unsignedSmallInteger('page')->default(1);
            $table->decimal('x', 8, 2);
            $table->decimal('y', 8, 2);
            $table->decimal('width', 8, 2);
            $table->decimal('height', 8, 2);

            // Apariencia
            $table->unsignedTinyInteger('font_size')->default(10);
            $table->enum('align', ['left', 'center', 'right'])->default('left');

            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['document_template_version_id', 'key'], 'tpl_fields_version_key_unique');
            $table->index('tenant_id');
            $table->index(['document_template_version_id', 'order'], 'tpl_fields_version_order_index');
        });

        Schema::create('document_template_signers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignId('document_template_version_id')
                ->constrained('document_template_versions')
                ->cascadeOnDelete();

            $table->string('role_key', 64)->comment('Ej: arrendador, arrendatario');
            $table->string('label');
            $table->unsignedSmallInteger('order')->default(0);

            // Donde firma este rol, en milimetros. Nulo: se usa la posicion
            // por defecto de config/signing.php
            $table->unsignedSmallInteger('signature_page')->nullable();
            $table->decimal('signature_x', 8, 2)->nullable();
            $table->decimal('signature_y', 8, 2)->nullable();

            $table->timestamps();

            $table->unique(['document_template_version_id', 'role_key'], 'tpl_signers_version_role_unique');
            $table->index('tenant_id');
        });

        Schema::table('signing_processes', function (Blueprint $table) {
            $table->foreignId('document_template_version_id')
                ->nullable()
                ->after('document_id')
                ->constrained('document_template_versions')
                ->nullOnDelete();
            $table->text('template_values')
                ->nullable()
                ->after('document_template_version_id')
                ->comment('Valores usados para generar el documento. Cifrado en reposo.');
        });
    }

    public function down(): void
    {
        Schema::table('signing_processes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_template_version_id');
            $table->dropColumn('template_values');
        });

        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_version_id');
        });

        Schema::dropIfExists('document_template_signers');
        Schema::dropIfExists('document_template_fields');
        Schema::dropIfExists('document_template_versions');
        Schema::dropIfExists('document_templates');
    }
};

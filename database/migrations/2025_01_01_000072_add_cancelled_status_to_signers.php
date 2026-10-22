<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Anade 'cancelled' a los estados posibles de un firmante.
 *
 * Al cancelar un proceso se marcan asi sus firmantes pendientes, pero el
 * ENUM no contemplaba ese valor: en MySQL la actualizacion se rechazaba y
 * cancelar un proceso quedaba a medias. En SQLite la columna es un varchar
 * sin restriccion, por eso los tests no lo veian.
 */
return new class extends Migration
{
    private const ESTADOS = "'pending','sent','viewed','signed','rejected','cancelled'";

    private const ESTADOS_PREVIOS = "'pending','sent','viewed','signed','rejected'";

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE signers MODIFY status ENUM('.self::ESTADOS.") NOT NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Los que estuvieran cancelados pasan a rechazados: es el estado
        // terminal mas parecido que si cabe en el enum anterior.
        DB::table('signers')->where('status', 'cancelled')->update(['status' => 'rejected']);

        DB::statement(
            'ALTER TABLE signers MODIFY status ENUM('.self::ESTADOS_PREVIOS.") NOT NULL DEFAULT 'pending'"
        );
    }
};

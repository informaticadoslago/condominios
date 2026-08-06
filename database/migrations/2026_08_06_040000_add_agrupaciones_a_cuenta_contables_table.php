<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El plan de cuentas guarda ahora también los grupos (1 cifra) y subgrupos (2) del PGC,
 * con su código corto: son los que agrupan a las cuentas de 3 cifras, que entre ellas son
 * hermanas (120, 121 y 129 cuelgan del subgrupo 12, no unas de otras).
 *
 * Un grupo no tiene naturaleza única —del 4 cuelgan clientes (activo) y proveedores
 * (pasivo)—, así que el tipo pasa a poder quedarse vacío en esas filas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuenta_contables', function (Blueprint $tabla) {
            $tabla->unsignedBigInteger('tipo_cuenta_contable_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cuenta_contables', function (Blueprint $tabla) {
            $tabla->unsignedBigInteger('tipo_cuenta_contable_id')->nullable(false)->change();
        });
    }
};

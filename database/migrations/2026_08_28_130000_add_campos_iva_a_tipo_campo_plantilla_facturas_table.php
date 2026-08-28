<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sociedades sí desglosa IVA en sus facturas (a diferencia de comunidad, que factura el
 * gasto por importe total): añade los tipos de campo que le hacen falta a la plantilla.
 * IMPORTE (3) no se toca, lo sigue usando comunidad tal cual.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tipo_campo_plantilla_facturas')->insert([
            ['id' => 6, 'descripcion' => 'Importe base'],
            ['id' => 7, 'descripcion' => 'Importe total'],
            ['id' => 8, 'descripcion' => 'Cuota de IVA'],
        ]);
    }

    public function down(): void
    {
        DB::table('tipo_campo_plantilla_facturas')->whereIn('id', [6, 7, 8])->delete();
    }
};

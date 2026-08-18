<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Fijar" congela el reparto por inmueble y pago (pantalla de Reparto) sin llegar a
 * aprobar el presupuesto ni generar recibos: a partir de ahí deja de recalcularse en
 * vivo y se puede corregir a mano, célula a célula. `reparto_fijado` guarda ese
 * desglose: {inmueble_id: [importe_pago_1, importe_pago_2, ...]}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->boolean('fijado')->default(false)->after('porcentajes_pago');
            $table->json('reparto_fijado')->nullable()->after('fijado');
        });
    }

    public function down(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->dropColumn(['fijado', 'reparto_fijado']);
        });
    }
};

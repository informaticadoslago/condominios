<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El importe de cada pago no se decide aquí: depende de cómo lo reparta cada grupo de
 * reparto (eso solo se sabe en la pantalla de Reparto). Lo único que se puede fijar de
 * antemano es qué porcentaje del presupuesto representa cada pago; el importe en euros
 * siempre se calcula a partir de ese porcentaje, no se guarda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->renameColumn('importes_pago', 'porcentajes_pago');
        });
    }

    public function down(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->renameColumn('porcentajes_pago', 'importes_pago');
        });
    }
};

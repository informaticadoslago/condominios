<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Etiqueta opaca de quien pidió la subcuenta, igual que la que ya llevan los terceros.
     *
     * Hace falta para las subcuentas que no son de nadie: la de ingresos de un
     * presupuesto o de una derrama no cuelga de ningún tercero, pero quien la pide tiene
     * que poder volver a pedirla y recibir la misma, no una nueva cada vez. La
     * contabilidad guarda y compara estos dos campos, no los interpreta.
     */
    public function up(): void
    {
        Schema::table('cuenta_contables', function (Blueprint $table) {
            $table->string('sujeto_tipo', 50)->nullable()->after('nombre');
            $table->string('sujeto_id', 100)->nullable()->after('sujeto_tipo');

            // Un sujeto, una subcuenta dentro de la empresa. Las cuentas del plan no
            // llevan sujeto y quedan fuera del único (varios nulos no chocan entre sí).
            $table->unique(['empresa_contable_id', 'sujeto_tipo', 'sujeto_id'], 'cuenta_contables_sujeto_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cuenta_contables', function (Blueprint $table) {
            $table->dropUnique('cuenta_contables_sujeto_unique');
            $table->dropColumn(['sujeto_tipo', 'sujeto_id']);
        });
    }
};

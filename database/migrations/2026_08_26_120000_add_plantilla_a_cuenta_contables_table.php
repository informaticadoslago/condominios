<?php

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
        Schema::table('cuenta_contables', function (Blueprint $table) {
            // Solo tiene sentido en las cuentas maestra (empresa_contable_id nulo): a qué
            // plantilla de arranque pertenece esta fila, además de la común (null). Puede
            // haber dos filas maestra con el mismo código -una común y otra de una
            // plantilla concreta-, para que la de la plantilla pise el nombre de la común
            // al copiar (ver CuentaContable::copiarPlanGlobalA). El unique de
            // (empresa_contable_id, codigo) no lo impide: MySQL no lo aplica cuando
            // empresa_contable_id es nulo.
            $table->string('plantilla', 30)->nullable()->after('codigo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuenta_contables', function (Blueprint $table) {
            $table->dropColumn('plantilla');
        });
    }
};

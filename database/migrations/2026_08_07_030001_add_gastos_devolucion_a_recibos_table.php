<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que el propietario debe deja de ser solo su cuota: si le devuelven el recibo, la
     * comisión del banco se le repercute y también hay que cobrársela. `importe` no se
     * toca —es lo aprobado en junta— así que los gastos van aparte y el saldo los suma.
     *
     * `saldo` es una columna generada, y una generada solo puede leer columnas de su
     * propia fila: por eso el recibo lleva el acumulado de sus devoluciones aunque el
     * detalle de cada una viva en su línea de remesa.
     */
    public function up(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->dropIndex('recibos_saldo_index');
            $table->dropColumn('saldo');
        });

        Schema::table('recibos', function (Blueprint $table) {
            $table->decimal('gastos_devolucion', 12, 2)->default(0)->after('importe_pagado');
        });

        Schema::table('recibos', function (Blueprint $table) {
            $table->decimal('saldo', 12, 2)->storedAs('importe + gastos_devolucion - importe_pagado')
                ->after('gastos_devolucion')
                ->index('recibos_saldo_index');
        });
    }

    public function down(): void
    {
        Schema::table('recibos', function (Blueprint $table) {
            $table->dropIndex('recibos_saldo_index');
            $table->dropColumn(['saldo', 'gastos_devolucion']);
        });

        Schema::table('recibos', function (Blueprint $table) {
            $table->decimal('saldo', 12, 2)->storedAs('importe - importe_pagado')
                ->after('importe_pagado')
                ->index('recibos_saldo_index');
        });
    }
};

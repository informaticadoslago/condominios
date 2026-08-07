<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobros', function (Blueprint $table) {
            // Asiento en el que entró este movimiento de dinero. Los cobros de una misma
            // remesa comparten asiento —el banco abona una sola vez— así que comparten
            // este número; una transferencia suelta tiene el suyo.
            //
            // Sin FK a propósito: es una referencia opaca a un módulo que no conoce a la
            // gestión, igual que la de los recibos. Nulo = todavía no enlazado.
            $table->unsignedBigInteger('asiento_contable')->nullable()->after('importe')
                ->index('cobros_asiento_contable_index');
        });
    }

    public function down(): void
    {
        Schema::table('cobros', function (Blueprint $table) {
            $table->dropIndex('cobros_asiento_contable_index');
            $table->dropColumn('asiento_contable');
        });
    }
};

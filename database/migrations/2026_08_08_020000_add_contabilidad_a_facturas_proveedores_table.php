<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas_proveedores', function (Blueprint $table) {
            // La cuenta de gasto con la que se contabilizó, copiada del tipo del proveedor
            // en ese momento: si mañana el proveedor cambia de tipo, el asiento que ya se
            // hizo siguió siendo contra esta. Nula mientras no se haya contabilizado.
            $table->char('cuenta_gasto', 8)->nullable()->after('importe');

            // Asiento de la contabilidad en el que entró esta factura. Sin FK a propósito:
            // referencia opaca a un módulo que no conoce a la gestión, igual que en
            // recibos. Nulo = todavía no contabilizada.
            $table->unsignedBigInteger('asiento_contable')->nullable()->after('cuenta_gasto')
                ->index('facturas_proveedores_asiento_contable_index');
        });
    }

    public function down(): void
    {
        Schema::table('facturas_proveedores', function (Blueprint $table) {
            $table->dropIndex('facturas_proveedores_asiento_contable_index');
            $table->dropColumn(['cuenta_gasto', 'asiento_contable']);
        });
    }
};

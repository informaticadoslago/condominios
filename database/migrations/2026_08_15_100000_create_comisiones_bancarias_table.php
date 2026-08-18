<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comisión que carga el banco al liquidar una remesa (no una devolución: esa se
     * repercute al propietario y va por el circuito de recibos). Es un gasto de la
     * comunidad que no pasa por ningún recibo, así que necesita su propio hecho fechado.
     */
    public function up(): void
    {
        Schema::create('comisiones_bancarias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cuenta_bancaria_id')->index('comisiones_bancarias_cuenta_bancaria_id_foreign');
            // La remesa que la generó, cuando es nuestra: hoy casi nunca lo es (remesas
            // presentadas fuera de esta gestión), así que queda nullable.
            $table->unsignedBigInteger('remesa_id')->nullable()->index('comisiones_bancarias_remesa_id_foreign');
            $table->date('fecha');
            $table->string('concepto', 255)->nullable();
            // Nº de operación/factura que da el banco (p.ej. el FRA de ABANCA), para
            // poder casarla luego con el extracto.
            $table->string('referencia', 60)->nullable();
            // Asiento de la contabilidad en el que entró. Sin FK a propósito: referencia
            // opaca a un módulo que no conoce a la gestión, igual que en facturas y
            // recibos. Nulo = todavía no contabilizada.
            $table->unsignedBigInteger('asiento_contable')->nullable()->index('comisiones_bancarias_asiento_contable_index');
            $table->timestamps();

            $table->foreign('cuenta_bancaria_id')->references('id')->on('cuentas_bancarias')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('remesa_id')->references('id')->on('remesas')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comisiones_bancarias');
    }
};

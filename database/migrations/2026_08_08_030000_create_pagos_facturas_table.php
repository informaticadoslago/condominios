<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada salida de dinero sobre una factura de proveedor. Igual que en recibos, el pago
     * es un hecho fechado propio y no una casilla en la factura: una factura puede pagarse
     * en varias veces, y lo que se ve en la factura (`importe_pagado`) es la suma de estas
     * filas. Nunca se corrige ni se borra un pago: para deshacer se registra el contrario.
     */
    public function up(): void
    {
        Schema::create('pagos_facturas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('factura_proveedor_id')->index('pagos_facturas_factura_proveedor_id_foreign');
            // De dónde salió el dinero. Se guarda en el pago, no se deduce después: la
            // comunidad puede abrir otra cuenta mañana y el asiento ya está hecho.
            $table->unsignedBigInteger('cuenta_bancaria_id')->index('pagos_facturas_cuenta_bancaria_id_foreign');
            $table->date('fecha');
            $table->decimal('importe', 10, 2);
            // Referencia opaca al módulo contable, como en recibos y facturas. Nulo = el
            // pago no ha llegado a la contabilidad (comunidad que no la lleva).
            $table->unsignedBigInteger('asiento_contable')->nullable()->index('pagos_facturas_asiento_contable_index');
            $table->timestamps();

            $table->foreign('factura_proveedor_id')->references('id')->on('facturas_proveedores')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign('cuenta_bancaria_id')->references('id')->on('cuentas_bancarias')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_facturas');
    }
};

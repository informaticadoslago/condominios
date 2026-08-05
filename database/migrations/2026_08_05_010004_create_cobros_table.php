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
        Schema::create('cobros', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recibo_id')->index('cobros_recibo_id_foreign');
            // Canal por el que entró el dinero, del catálogo de formas de pago.
            $table->unsignedBigInteger('forma_de_pago_id')->index('cobros_forma_de_pago_id_foreign');
            // Solo si vino de una remesa; los cobros en mano o por transferencia no la tienen.
            $table->unsignedBigInteger('linea_remesa_id')->nullable()->index('cobros_linea_remesa_id_foreign');
            $table->date('fecha');

            // Con signo: positivo cobra, negativo devuelve. Así `importe_pagado` del
            // recibo es siempre la suma de sus cobros, sin restar nada por separado, y
            // cada fila —cobre o devuelva— es un hecho fechado que la contabilidad
            // referencia como «cobro:N» para su asiento.
            $table->decimal('importe', 12, 2);

            $table->timestamps();

            $table->foreign('recibo_id')->references('id')->on('recibos')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('forma_de_pago_id')->references('id')->on('formas_de_pago')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('linea_remesa_id')->references('id')->on('lineas_remesas')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cobros');
    }
};

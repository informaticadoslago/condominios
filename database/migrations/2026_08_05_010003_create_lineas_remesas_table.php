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
        Schema::create('lineas_remesas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('remesa_id')->index('lineas_remesas_remesa_id_foreign');
            $table->unsignedBigInteger('recibo_id')->index('lineas_remesas_recibo_id_foreign');

            // Siempre el importe completo del recibo: una devolución SEPA nunca es
            // parcial, el banco devuelve el adeudo entero. Los cobros parciales llegan
            // por otros canales y viven en `cobros`.
            $table->decimal('importe', 12, 2);

            // IBAN con el que se presentó, copiado al generar el fichero: el vigente
            // entonces, que puede no ser el que quedó congelado en el recibo.
            $table->string('iban', 34);

            $table->date('fecha_devolucion')->nullable();
            $table->string('motivo_devolucion', 100)->nullable();
            $table->timestamps();

            // Un recibo no puede ir dos veces en la misma remesa; en remesas distintas
            // sí (devuelto y vuelto a presentar), y cada intento es una línea.
            $table->unique(['remesa_id', 'recibo_id'], 'lineas_remesas_remesa_recibo_unique');

            $table->foreign('remesa_id')->references('id')->on('remesas')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('recibo_id')->references('id')->on('recibos')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lineas_remesas');
    }
};

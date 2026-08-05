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
        Schema::create('asiento_contables', function (Blueprint $table) {
            $table->id();
            // Denormalizado desde el ejercicio, y hace falta: el índice de idempotencia
            // tiene que abarcar la empresa entera. Si se limitara al ejercicio, la misma
            // referencia podría colarse dos veces en dos ejercicios distintos.
            $table->unsignedBigInteger('empresa_contable_id')->index('asiento_contables_empresa_contable_id_foreign');
            $table->unsignedBigInteger('ejercicio_contable_id')->index('asiento_contables_ejercicio_contable_id_foreign');
            $table->unsignedInteger('numero');
            $table->date('fecha');
            $table->string('diario', 10)->nullable();
            $table->string('concepto', 255);

            // Identifican el hecho externo que originó el asiento, y son la clave de
            // idempotencia. Texto libre a propósito: contabilidad no interpreta estos
            // valores ni mantiene una lista de eventos conocidos, solo los compara.
            // Nulos en los asientos manuales — un índice único admite nulos repetidos.
            $table->string('referencia_tipo', 50)->nullable();
            $table->string('referencia_id', 100)->nullable();
            $table->string('evento', 50)->nullable();

            $table->timestamps();

            $table->unique(['ejercicio_contable_id', 'numero']);
            $table->unique(
                ['empresa_contable_id', 'referencia_tipo', 'referencia_id', 'evento'],
                'asiento_contables_referencia_unique',
            );

            $table->foreign('empresa_contable_id')->references('id')->on('empresas_contables')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('ejercicio_contable_id')->references('id')->on('ejercicio_contables')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asiento_contables');
    }
};

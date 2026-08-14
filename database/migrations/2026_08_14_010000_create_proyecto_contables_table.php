<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dimensión analítica dentro de una empresa contable: no es una cuenta ni cuelga del
     * plan de cuentas, es una etiqueta que se puede poner en cualquier apunte (ver
     * apunte_contables.proyecto_contable_id) para poder sacar, sin salir del libro único
     * de la empresa, el balance de cada actividad por separado.
     *
     * Igual que las cuentas de ingreso o las de tesorería, se resuelve por sujeto opaco:
     * pedir dos veces el mismo sujeto devuelve el mismo proyecto, no uno nuevo.
     */
    public function up(): void
    {
        Schema::create('proyecto_contables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_contable_id')->index('proyecto_contables_empresa_contable_id_foreign');
            $table->string('nombre', 150);
            $table->string('sujeto_tipo', 50)->nullable();
            $table->string('sujeto_id', 100)->nullable();
            $table->timestamps();

            $table->unique(['empresa_contable_id', 'sujeto_tipo', 'sujeto_id'], 'proyecto_contables_sujeto_unique');

            $table->foreign('empresa_contable_id')->references('id')->on('empresas_contables')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proyecto_contables');
    }
};

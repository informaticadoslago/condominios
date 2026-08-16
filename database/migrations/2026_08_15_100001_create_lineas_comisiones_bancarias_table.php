<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El banco carga la comisión y su IVA en movimientos separados (y a veces uno por
     * cada grupo de recibos: propia entidad / otras entidades), así que cada uno es su
     * propia línea, no un importe único: hace falta poder casar cada una con el
     * extracto del banco.
     */
    public function up(): void
    {
        Schema::create('lineas_comisiones_bancarias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comision_bancaria_id')->index('lineas_comisiones_bancarias_comision_bancaria_id_foreign');
            $table->string('concepto', 120);
            $table->decimal('importe', 10, 2);
            $table->timestamps();

            $table->foreign('comision_bancaria_id')->references('id')->on('comisiones_bancarias')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lineas_comisiones_bancarias');
    }
};

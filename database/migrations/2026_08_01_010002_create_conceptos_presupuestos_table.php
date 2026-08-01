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
        Schema::create('conceptos_presupuestos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('presupuesto_id')->index('conceptos_presupuestos_presupuesto_id_foreign');
            $table->string('concepto', 150);
            $table->decimal('importe', 12, 2);
            $table->unsignedBigInteger('grupo_de_reparto_id')->index('conceptos_presupuestos_grupo_de_reparto_id_foreign');
            $table->timestamps();

            $table->foreign('presupuesto_id')->references('id')->on('presupuestos')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('grupo_de_reparto_id')->references('id')->on('grupos_de_reparto')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conceptos_presupuestos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Puntos del orden del día de una convocatoria de junta, en el orden en que se
     * tratan. `orden` empieza en 1 y lo fija el arrastre en el formulario. Son parte de
     * la convocatoria: se borran con ella (cascade), como las líneas de comisiones.
     */
    public function up(): void
    {
        Schema::create('lineas_orden_del_dia', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('convocatoria_junta_id')->index('lineas_orden_del_dia_convocatoria_junta_id_foreign');
            $table->unsignedSmallInteger('orden');
            $table->text('texto');
            $table->timestamps();

            $table->foreign('convocatoria_junta_id')->references('id')->on('convocatorias_juntas')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lineas_orden_del_dia');
    }
};

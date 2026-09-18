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
        Schema::create('horario_sesiones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('horario_id')->index('horario_sesiones_horario_id_foreign');
            $table->unsignedTinyInteger('dia_semana');
            $table->unsignedTinyInteger('sesion_numero');
            $table->unsignedBigInteger('asignatura_id')->index('horario_sesiones_asignatura_id_foreign');
            $table->timestamps();

            $table->unique(['horario_id', 'dia_semana', 'sesion_numero']);

            $table->foreign('horario_id')->references('id')->on('horarios')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign('asignatura_id')->references('id')->on('asignaturas')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horario_sesiones');
    }
};

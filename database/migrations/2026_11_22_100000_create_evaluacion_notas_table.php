<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nota de un alumno en una evaluación retocada a mano. La nota de la evaluación es la
     * media de sus pruebas según su peso y no se guarda; aquí solo va el retoque: la media
     * de la que partía (media) y la nota puesta a mano (nota). Si la media ya no es aquella,
     * el retoque deja de valer. El alumno se guarda por su apunte en la asignatura
     * (asignatura_alumnos), que dice también de qué asignatura es la nota.
     */
    public function up(): void
    {
        Schema::create('evaluacion_notas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evaluacion_id')->index('evaluacion_notas_evaluacion_id_foreign');
            $table->unsignedBigInteger('asignatura_alumno_id')->index('evaluacion_notas_asignatura_alumno_id_foreign');
            $table->decimal('media', 4, 2);
            $table->decimal('nota', 4, 2);
            $table->timestamps();

            $table->unique(['evaluacion_id', 'asignatura_alumno_id']);

            $table->foreign('evaluacion_id')->references('id')->on('evaluaciones')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('asignatura_alumno_id')->references('id')->on('asignatura_alumnos')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluacion_notas');
    }
};

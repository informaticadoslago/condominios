<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pruebas evaluables de una asignatura en una evaluación. puntuacion_maxima es la «X»
     * sobre la que se puntúa (30 si son 30 preguntas de un punto), la misma para todos los
     * alumnos; peso es el porcentaje que la prueba cuenta en la evaluación.
     */
    public function up(): void
    {
        Schema::create('pruebas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asignatura_id')->index('pruebas_asignatura_id_foreign');
            $table->unsignedBigInteger('evaluacion_id')->index('pruebas_evaluacion_id_foreign');
            $table->string('nombre', 100);
            $table->date('fecha');
            $table->decimal('puntuacion_maxima', 6, 2);
            $table->decimal('peso', 5, 2);
            $table->unsignedBigInteger('estado_id')->default(1)->index('pruebas_estado_id_foreign');
            $table->timestamps();

            $table->unique(['asignatura_id', 'evaluacion_id', 'nombre']);

            $table->foreign('asignatura_id')->references('id')->on('asignaturas')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('evaluacion_id')->references('id')->on('evaluaciones')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pruebas');
    }
};

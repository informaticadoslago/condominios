<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nota de un alumno en una prueba. El alumno se guarda por su apunte en la asignatura
     * (asignatura_alumnos), que es la de la prueba. nota es sobre la puntuación máxima de
     * la prueba; nota_sobre_10 se calcula de ella, pero se puede retocar a mano (para
     * dejarla en un entero) y entonces deja de coincidir con el cálculo.
     */
    public function up(): void
    {
        Schema::create('prueba_notas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('prueba_id')->index('prueba_notas_prueba_id_foreign');
            $table->unsignedBigInteger('asignatura_alumno_id')->index('prueba_notas_asignatura_alumno_id_foreign');
            $table->decimal('nota', 6, 2)->nullable();
            $table->decimal('nota_sobre_10', 4, 2);
            $table->timestamps();

            $table->unique(['prueba_id', 'asignatura_alumno_id']);

            $table->foreign('prueba_id')->references('id')->on('pruebas')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('asignatura_alumno_id')->references('id')->on('asignatura_alumnos')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prueba_notas');
    }
};

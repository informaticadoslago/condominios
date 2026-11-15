<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alumnos de un horario de profesor. Son del horario y no de una asignatura: el mismo
     * alumno se apunta a varias asignaturas del horario (ver asignatura_alumnos).
     *
     * Nombre, los dos apellidos y fecha de nacimiento no se repiten dentro del horario:
     * si coinciden los cuatro, es el mismo alumno. Para que el índice único lo vea,
     * apellido2 se guarda vacío y no nulo en quien no tiene segundo apellido (MySQL no
     * compara los NULL en un índice único).
     */
    public function up(): void
    {
        Schema::create('alumnos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('horario_id')->index('alumnos_horario_id_foreign');
            $table->string('nombre', 50);
            $table->string('apellido1', 50);
            $table->string('apellido2', 50)->default('');
            $table->date('fecha_nacimiento');
            $table->timestamps();

            $table->unique(['horario_id', 'nombre', 'apellido1', 'apellido2', 'fecha_nacimiento'], 'alumnos_identidad_unique');

            $table->foreign('horario_id')->references('id')->on('horarios')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumnos');
    }
};

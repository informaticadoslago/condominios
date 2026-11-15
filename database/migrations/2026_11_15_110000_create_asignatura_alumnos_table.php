<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Apunte de un alumno en una asignatura, con el grupo en el que la cursa: una misma
     * asignatura puede darse a alumnos del mismo módulo pero de varios grupos. Quitar a un
     * alumno de la asignatura es dejar su apunte inactivo, no borrarlo.
     */
    public function up(): void
    {
        Schema::create('asignatura_alumnos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asignatura_id')->index('asignatura_alumnos_asignatura_id_foreign');
            $table->unsignedBigInteger('alumno_id')->index('asignatura_alumnos_alumno_id_foreign');
            $table->string('grupo', 10);
            $table->unsignedBigInteger('estado_id')->default(1)->index('asignatura_alumnos_estado_id_foreign');
            $table->timestamps();

            $table->unique(['asignatura_id', 'alumno_id']);

            $table->foreign('asignatura_id')->references('id')->on('asignaturas')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('alumno_id')->references('id')->on('alumnos')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignatura_alumnos');
    }
};

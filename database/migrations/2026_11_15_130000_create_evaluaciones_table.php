<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evaluaciones de un curso. Dentro del curso no se repite el nombre, sus fechas caen
     * dentro de las del curso y no se solapan entre sí; las fechas se comprueban desde la
     * aplicación (ver App\Models\Evaluacion::seSolapa).
     */
    public function up(): void
    {
        Schema::create('evaluaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('curso_id')->index('evaluaciones_curso_id_foreign');
            $table->string('nombre', 100);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->unsignedBigInteger('estado_id')->default(1)->index('evaluaciones_estado_id_foreign');
            $table->timestamps();

            $table->unique(['curso_id', 'nombre']);

            $table->foreign('curso_id')->references('id')->on('cursos')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cursos de un horario de profesor (2026/2027…). Dentro de un horario no se repite el
     * nombre ni se solapan las fechas: un día cae como mucho en un curso. Lo segundo se
     * comprueba desde la aplicación (ver App\Models\Curso::seSolapa).
     */
    public function up(): void
    {
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('horario_id')->index('cursos_horario_id_foreign');
            $table->string('nombre', 100);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->unsignedBigInteger('estado_id')->default(1)->index('cursos_estado_id_foreign');
            $table->timestamps();

            $table->unique(['horario_id', 'nombre']);

            $table->foreign('horario_id')->references('id')->on('horarios')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cursos');
    }
};

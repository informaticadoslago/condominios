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
        // Sustituida por la tabla jornadas: una jornada ya no es "un día concreto", es
        // una plantilla (nombre, hora de inicio, sesiones, recreo) que se asigna a los
        // días de la semana en los que se repite.
        Schema::dropIfExists('horario_dias');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('horario_dias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('horario_id')->index('horario_dias_horario_id_foreign');
            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_primera_sesion');
            $table->unsignedTinyInteger('num_sesiones');
            $table->unsignedTinyInteger('recreo_antes_de_sesion')->nullable();
            $table->unsignedSmallInteger('recreo_duracion_minutos')->nullable();
            $table->timestamps();

            $table->unique(['horario_id', 'dia_semana']);

            $table->foreign('horario_id')->references('id')->on('horarios')->onUpdate('restrict')->onDelete('cascade');
        });
    }
};

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
        Schema::create('jornadas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('horario_id')->index('jornadas_horario_id_foreign');
            $table->string('nombre', 100);
            $table->time('hora_inicio');
            $table->unsignedTinyInteger('num_sesiones');
            // Ambos nulos = esta jornada no tiene recreo. El recreo va ANTES de la sesión
            // indicada, así que siempre deja sesiones a los dos lados.
            $table->unsignedTinyInteger('recreo_antes_de_sesion')->nullable();
            $table->unsignedSmallInteger('recreo_duracion_minutos')->nullable();
            // [1,2,3,4,5] (lunes..domingo, ver App\Support\DiaSemana): los días de la
            // semana en los que se da esta jornada. Una jornada puede repetirse en varios
            // días, y un día puede tener varias jornadas (no deberían solaparse en hora,
            // pero no se impide).
            $table->json('dias_semana');
            $table->timestamps();

            $table->foreign('horario_id')->references('id')->on('horarios')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jornadas');
    }
};

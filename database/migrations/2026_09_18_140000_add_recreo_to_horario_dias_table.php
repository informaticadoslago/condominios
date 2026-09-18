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
        Schema::table('horario_dias', function (Blueprint $table) {
            // Ambos nulos = ese día no tiene recreo. El recreo va ANTES de la sesión
            // indicada (p.ej. 3 => sesiones 1 y 2 normales, recreo, y luego la 3 en
            // adelante ya desplazadas), así que siempre deja sesiones a los dos lados.
            $table->unsignedTinyInteger('recreo_antes_de_sesion')->nullable()->after('num_sesiones');
            $table->unsignedSmallInteger('recreo_duracion_minutos')->nullable()->after('recreo_antes_de_sesion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('horario_dias', function (Blueprint $table) {
            $table->dropColumn(['recreo_antes_de_sesion', 'recreo_duracion_minutos']);
        });
    }
};

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
        Schema::table('jornadas', function (Blueprint $table) {
            // Segundo recreo, igual que el primero: ambos nulos = no hay. Va siempre
            // después del primero (recreo2_antes_de_sesion > recreo_antes_de_sesion).
            $table->unsignedTinyInteger('recreo2_antes_de_sesion')->nullable()->after('recreo_duracion_minutos');
            $table->unsignedSmallInteger('recreo2_duracion_minutos')->nullable()->after('recreo2_antes_de_sesion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jornadas', function (Blueprint $table) {
            $table->dropColumn(['recreo2_antes_de_sesion', 'recreo2_duracion_minutos']);
        });
    }
};

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
        Schema::table('horario_sesiones', function (Blueprint $table) {
            // Solo en horarios de profesor: hora de inicio puesta a mano (arrastrando la
            // sesión en el horario semanal o en su formulario). Nulo = la que le toca por
            // su jornada.
            $table->time('hora_inicio')->nullable()->after('sesion_numero');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('horario_sesiones', function (Blueprint $table) {
            $table->dropColumn('hora_inicio');
        });
    }
};

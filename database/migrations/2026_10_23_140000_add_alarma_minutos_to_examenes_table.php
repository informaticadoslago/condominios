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
        // Minutos antes del inicio para la alarma del evento en el calendario (.ics);
        // nulo = sin alarma. Ver Examen::opcionesAlarma().
        Schema::table('examenes', function (Blueprint $table) {
            $table->unsignedInteger('alarma_minutos')->nullable()->after('hora_fin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('examenes', function (Blueprint $table) {
            $table->dropColumn('alarma_minutos');
        });
    }
};

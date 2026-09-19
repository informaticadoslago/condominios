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
        Schema::table('horarios', function (Blueprint $table) {
            // Por defecto (false) cada día lista sus sesiones seguidas, desde su propia
            // fila 0. Con esto a true, la rejilla alinea horizontalmente la misma hora
            // real en los 7 días: deja huecos (filas sin sesión, no asignables) en los
            // días donde a esa hora no hay clase.
            $table->boolean('alinear_horas')->default(false)->after('duracion_sesion_minutos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('horarios', function (Blueprint $table) {
            $table->dropColumn('alinear_horas');
        });
    }
};

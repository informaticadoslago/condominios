<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fecha que lleva la carta de convocatoria ("Vigo, 19 de octubre de 2026"): la del día
     * en que se generó el PDF. Nula = todavía sin generar. Regenerarla le pone la del día.
     * El PDF se guarda en disco (ver ConvocatoriaJunta::rutaCarta), no en la tabla.
     */
    public function up(): void
    {
        Schema::table('convocatorias_juntas', function (Blueprint $table) {
            $table->date('fecha_carta')->nullable()->after('hora_segunda_convocatoria');
        });
    }

    public function down(): void
    {
        Schema::table('convocatorias_juntas', function (Blueprint $table) {
            $table->dropColumn('fecha_carta');
        });
    }
};

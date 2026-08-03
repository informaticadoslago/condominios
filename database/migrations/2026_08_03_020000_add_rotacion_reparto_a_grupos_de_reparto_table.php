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
        Schema::table('grupos_de_reparto', function (Blueprint $table) {
            // Posición (0-indexada, en el orden planta/puerta de sus miembros) en la que
            // el próximo reparto debe empezar a repartir los céntimos sobrantes: ver
            // Presupuesto::repartirProporcional()/avanzarRotacionReparto(). Presupuestos
            // anuales y derramas comparten esta misma rotación por grupo.
            $table->unsignedInteger('siguiente_inicio_reparto')->default(0)->after('nombre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grupos_de_reparto', function (Blueprint $table) {
            $table->dropColumn('siguiente_inicio_reparto');
        });
    }
};

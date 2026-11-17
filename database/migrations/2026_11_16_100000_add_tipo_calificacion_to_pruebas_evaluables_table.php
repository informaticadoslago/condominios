<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cómo se califica la prueba: 1 con un número (lo que había), 2 con Apto / No apto y
     * 3 con Entregado / No entregado. Las dos últimas son pruebas sobre 1 (puntuacion_maxima
     * 1): su nota es 1 o 0 y la de 10, 10 o 0; lo que cambia es cómo se enseñan.
     */
    public function up(): void
    {
        Schema::table('pruebas_evaluables', function (Blueprint $table) {
            $table->unsignedTinyInteger('tipo_calificacion')->default(1)->after('puntuacion_maxima');
        });
    }

    public function down(): void
    {
        Schema::table('pruebas_evaluables', function (Blueprint $table) {
            $table->dropColumn('tipo_calificacion');
        });
    }
};

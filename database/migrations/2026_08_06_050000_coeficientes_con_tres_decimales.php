<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los coeficientes pasan de dos a tres decimales: con dos, en comunidades de muchos
 * inmuebles no hay forma de que el reparto cuadre exactamente en el 100%.
 *
 * 6,3 en vez de 5,3 porque el 100,000 necesita las seis cifras. Afecta tanto al
 * coeficiente propio del inmueble como al que puede fijársele dentro de un grupo
 * de reparto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inmuebles', function (Blueprint $table) {
            $table->decimal('coeficiente', 6, 3)->change();
        });

        Schema::table('inmueble_grupo_de_reparto', function (Blueprint $table) {
            $table->decimal('coeficiente', 6, 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('inmuebles', function (Blueprint $table) {
            $table->decimal('coeficiente', 5, 2)->change();
        });

        Schema::table('inmueble_grupo_de_reparto', function (Blueprint $table) {
            $table->decimal('coeficiente', 5, 2)->nullable()->change();
        });
    }
};

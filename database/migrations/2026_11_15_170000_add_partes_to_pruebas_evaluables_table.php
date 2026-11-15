<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pruebas con partes: una prueba puede ir dentro de otra (prueba_id), que entonces es
     * una prueba «con partes» (con_partes): no tiene puntuación máxima ni notas propias, y
     * su nota es la media de sus partes según el peso de cada una dentro de ella.
     * nota_minima es la que hay que sacar en cada parte para que la media valga (0 = sin
     * mínimo). Solo hay un nivel: una parte no tiene partes.
     */
    public function up(): void
    {
        Schema::table('pruebas_evaluables', function (Blueprint $table) {
            $table->unsignedBigInteger('prueba_id')->nullable()->after('evaluacion_id')->index('pruebas_evaluables_prueba_id_foreign');
            $table->boolean('con_partes')->default(false)->after('peso');
            $table->decimal('nota_minima', 4, 2)->default(5)->after('con_partes');
            $table->decimal('puntuacion_maxima', 6, 2)->nullable()->change();

            $table->foreign('prueba_id')->references('id')->on('pruebas_evaluables')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('pruebas_evaluables', function (Blueprint $table) {
            $table->dropForeign(['prueba_id']);
            $table->dropColumn(['prueba_id', 'con_partes', 'nota_minima']);
            $table->decimal('puntuacion_maxima', 6, 2)->nullable(false)->change();
        });
    }
};

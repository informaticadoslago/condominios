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
        Schema::table('dosl_direcciones', function (Blueprint $table) {
            $table->foreign(['estado_id'])->references(['id'])->on('estados')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['municipio_id'])->references(['id'])->on('municipios')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['pais_id'])->references(['id'])->on('paises')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['poblacion_id'])->references(['id'])->on('poblaciones')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['provincia_id'])->references(['id'])->on('provincias')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['tipo_direccion_id'])->references(['id'])->on('tipo_direcciones')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['via_id'])->references(['id'])->on('vias')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dosl_direcciones', function (Blueprint $table) {
            $table->dropForeign('dosl_direcciones_estado_id_foreign');
            $table->dropForeign('dosl_direcciones_municipio_id_foreign');
            $table->dropForeign('dosl_direcciones_pais_id_foreign');
            $table->dropForeign('dosl_direcciones_poblacion_id_foreign');
            $table->dropForeign('dosl_direcciones_provincia_id_foreign');
            $table->dropForeign('dosl_direcciones_tipo_direccion_id_foreign');
            $table->dropForeign('dosl_direcciones_via_id_foreign');
        });
    }
};

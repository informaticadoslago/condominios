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
        Schema::table('personas', function (Blueprint $table) {
            $table->foreign(['documento_pais_id'])->references(['id'])->on('paises')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['estado_id'])->references(['id'])->on('estado_personas')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign(['genero_id'])->references(['id'])->on('tipo_generos')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['nacionalidad_id'])->references(['id'])->on('paises')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['nif_pais_id'])->references(['id'])->on('paises')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['tipo_documento_id'])->references(['id'])->on('tipo_documento_identificativos')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropForeign('personas_documento_pais_id_foreign');
            $table->dropForeign('personas_estado_id_foreign');
            $table->dropForeign('personas_genero_id_foreign');
            $table->dropForeign('personas_nacionalidad_id_foreign');
            $table->dropForeign('personas_nif_pais_id_foreign');
            $table->dropForeign('personas_tipo_documento_id_foreign');
        });
    }
};

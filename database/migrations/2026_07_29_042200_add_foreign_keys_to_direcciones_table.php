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
        Schema::table('direcciones', function (Blueprint $table) {
            $table->foreign(['estado_id'])->references(['id'])->on('estado_usuarios')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign(['persona_id'])->references(['id'])->on('personas')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['tipo_direccion_id'])->references(['id'])->on('tipos_de_tipos')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['via_id'])->references(['id'])->on('vias')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('direcciones', function (Blueprint $table) {
            $table->dropForeign('direcciones_estado_id_foreign');
            $table->dropForeign('direcciones_persona_id_foreign');
            $table->dropForeign('direcciones_tipo_direccion_id_foreign');
            $table->dropForeign('direcciones_via_id_foreign');
        });
    }
};

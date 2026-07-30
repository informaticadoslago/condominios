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
        Schema::table('socios', function (Blueprint $table) {
            $table->foreign(['anualidad_socio_id'])->references(['id'])->on('anualidad_socios')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['contactoateneo_id'])->references(['id'])->on('socios')->onUpdate('restrict')->onDelete('set null');
            $table->foreign(['entidad_bancaria_id'])->references(['id'])->on('entidades_bancarias')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['estado_id'])->references(['id'])->on('estado_socios')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign(['forma_de_pago_id'])->references(['id'])->on('formas_de_pago')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['persona_id'])->references(['id'])->on('personas')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['tipo_entidadfinanciera_id'])->references(['id'])->on('tipos_de_tipos')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['tipo_formadepago_id'])->references(['id'])->on('tipos_de_tipos')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['titularcb_id'])->references(['id'])->on('personas')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('socios', function (Blueprint $table) {
            $table->dropForeign('socios_anualidad_socio_id_foreign');
            $table->dropForeign('socios_contactoateneo_id_foreign');
            $table->dropForeign('socios_entidad_bancaria_id_foreign');
            $table->dropForeign('socios_estado_id_foreign');
            $table->dropForeign('socios_forma_de_pago_id_foreign');
            $table->dropForeign('socios_persona_id_foreign');
            $table->dropForeign('socios_tipo_entidadfinanciera_id_foreign');
            $table->dropForeign('socios_tipo_formadepago_id_foreign');
            $table->dropForeign('socios_titularcb_id_foreign');
        });
    }
};

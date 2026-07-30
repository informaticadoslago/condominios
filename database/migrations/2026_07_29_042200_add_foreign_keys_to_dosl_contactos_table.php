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
        Schema::table('dosl_contactos', function (Blueprint $table) {
            $table->foreign(['estado_id'])->references(['id'])->on('estados')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['tipo_contacto_id'])->references(['id'])->on('tipo_contactos')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dosl_contactos', function (Blueprint $table) {
            $table->dropForeign('dosl_contactos_estado_id_foreign');
            $table->dropForeign('dosl_contactos_tipo_contacto_id_foreign');
        });
    }
};

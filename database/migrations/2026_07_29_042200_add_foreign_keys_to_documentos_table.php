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
        Schema::table('documentos', function (Blueprint $table) {
            $table->foreign(['estado_id'])->references(['id'])->on('estados')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['tipo_documento_id'])->references(['id'])->on('tipo_documentos')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropForeign('documentos_estado_id_foreign');
            $table->dropForeign('documentos_tipo_documento_id_foreign');
        });
    }
};

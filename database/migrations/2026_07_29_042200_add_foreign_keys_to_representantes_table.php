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
        Schema::table('representantes', function (Blueprint $table) {
            $table->foreign(['persona_id'])->references(['id'])->on('personas')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign(['representante_id'])->references(['id'])->on('personas')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('representantes', function (Blueprint $table) {
            $table->dropForeign('representantes_persona_id_foreign');
            $table->dropForeign('representantes_representante_id_foreign');
        });
    }
};

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
        Schema::table('poblaciones', function (Blueprint $table) {
            $table->foreign(['municipio_id'])->references(['id'])->on('municipios')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('poblaciones', function (Blueprint $table) {
            $table->dropForeign('poblaciones_municipio_id_foreign');
        });
    }
};

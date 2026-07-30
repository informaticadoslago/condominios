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
        Schema::table('entidades_bancarias', function (Blueprint $table) {
            $table->foreign(['estado_id'])->references(['id'])->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entidades_bancarias', function (Blueprint $table) {
            $table->dropForeign('entidades_bancarias_estado_id_foreign');
        });
    }
};

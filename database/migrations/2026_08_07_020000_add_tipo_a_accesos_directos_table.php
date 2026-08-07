<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distingue la ficha de entrada a una comunidad o a una empresa contable del
     * acceso directo de siempre (una entrada del menú). Solo sirve para pintarlas
     * separadas y con su color: el destino sigue siendo la url.
     */
    public function up(): void
    {
        Schema::table('accesos_directos', function (Blueprint $table) {
            $table->string('tipo', 20)->default('menu')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('accesos_directos', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};

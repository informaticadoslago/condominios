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
        Schema::table('users', function (Blueprint $table) {
            $table->foreign(['estado_id'])->references(['id'])->on('estado_usuarios')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign(['persona_id'])->references(['id'])->on('personas')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_estado_id_foreign');
            $table->dropForeign('users_persona_id_foreign');
        });
    }
};

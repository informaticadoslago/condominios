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
            // Sí/no general del usuario a las notificaciones push (se cambia en su perfil).
            // Con "no" no se le pregunta en ningún navegador y no tiene suscripciones.
            $table->boolean('notificaciones_push')->default(false)->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notificaciones_push');
        });
    }
};

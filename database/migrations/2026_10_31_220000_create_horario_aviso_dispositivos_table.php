<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A qué dispositivos de un usuario van los avisos de sesión de un horario. Sin filas, a
     * todos (también a los que añada después); con filas, solo a esos.
     */
    public function up(): void
    {
        Schema::create('horario_aviso_dispositivos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index('horario_aviso_dispositivos_user_id_foreign');
            $table->unsignedBigInteger('horario_id')->index('horario_aviso_dispositivos_horario_id_foreign');
            $table->unsignedBigInteger('push_subscription_id')->index('horario_aviso_dispositivos_push_subscription_id_foreign');
            $table->timestamps();

            $table->unique(['user_id', 'horario_id', 'push_subscription_id'], 'horario_aviso_dispositivos_unico');

            $table->foreign('user_id')->references('id')->on('users')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign('horario_id')->references('id')->on('horarios')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign('push_subscription_id')->references('id')->on('push_subscriptions')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horario_aviso_dispositivos');
    }
};

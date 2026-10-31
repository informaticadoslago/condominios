<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los avisos push que quiere cada usuario en una sesión de un horario: uno por momento
     * ('inicio' o 'fin') con sus minutos de antelación. hora_aviso es el minuto en que
     * salta, ya calculado al guardar (y otra vez cada vez que se mueven las sesiones), para
     * que el cron solo tenga que buscar el día y la hora; null si la sesión ya no cae en
     * ninguna jornada.
     */
    public function up(): void
    {
        Schema::create('horario_sesion_avisos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index('horario_sesion_avisos_user_id_foreign');
            $table->unsignedBigInteger('horario_id')->index('horario_sesion_avisos_horario_id_foreign');
            $table->unsignedBigInteger('horario_sesion_id')->index('horario_sesion_avisos_horario_sesion_id_foreign');
            $table->string('momento', 6);
            $table->unsignedSmallInteger('minutos_antes');
            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_aviso')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'horario_sesion_id', 'momento'], 'horario_sesion_avisos_unico');
            $table->index(['dia_semana', 'hora_aviso'], 'horario_sesion_avisos_cuando');

            $table->foreign('user_id')->references('id')->on('users')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign('horario_id')->references('id')->on('horarios')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign('horario_sesion_id')->references('id')->on('horario_sesiones')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horario_sesion_avisos');
    }
};

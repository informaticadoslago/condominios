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
        Schema::create('horario_dias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('horario_id')->index('horario_dias_horario_id_foreign');
            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_primera_sesion');
            $table->unsignedTinyInteger('num_sesiones');
            $table->timestamps();

            $table->unique(['horario_id', 'dia_semana']);

            $table->foreign('horario_id')->references('id')->on('horarios')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horario_dias');
    }
};

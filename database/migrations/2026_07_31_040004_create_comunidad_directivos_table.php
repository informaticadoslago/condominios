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
        Schema::create('comunidad_directivos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comunidad_id')->index('comunidad_directivos_comunidad_id_foreign');
            $table->unsignedBigInteger('persona_comunidad_id')->index('comunidad_directivos_persona_comunidad_id_foreign');
            $table->string('puesto', 100);
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->timestamps();

            $table->foreign('comunidad_id')->references('id')->on('comunidades')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('persona_comunidad_id')->references('id')->on('personas_comunidad')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comunidad_directivos');
    }
};

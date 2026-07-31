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
        Schema::create('inmueble_grupo_de_reparto', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grupo_de_reparto_id')->index('inmueble_grupo_de_reparto_grupo_de_reparto_id_foreign');
            $table->unsignedBigInteger('inmueble_id')->index('inmueble_grupo_de_reparto_inmueble_id_foreign');
            $table->timestamps();

            $table->unique(['grupo_de_reparto_id', 'inmueble_id'], 'inmueble_grupo_de_reparto_unique');
            $table->foreign('grupo_de_reparto_id', 'inmueble_grupo_de_reparto_grupo_fk')->references('id')->on('grupos_de_reparto')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('inmueble_id', 'inmueble_grupo_de_reparto_inmueble_fk')->references('id')->on('inmuebles')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inmueble_grupo_de_reparto');
    }
};

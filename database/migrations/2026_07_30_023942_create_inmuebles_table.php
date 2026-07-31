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
        Schema::create('inmuebles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comunidad_id')->index('inmuebles_comunidad_id_foreign');
            $table->unsignedBigInteger('ocupacion_id')->index('inmuebles_ocupacion_id_foreign');
            $table->unsignedBigInteger('tipo_inmueble_id')->index('inmuebles_tipo_inmueble_id_foreign');
            $table->tinyInteger('planta');
            $table->string('puerta', 5);
            $table->decimal('coeficiente', 5, 2);
            $table->string('referencia_catastral', 20)->nullable();
            $table->timestamps();

            $table->foreign('comunidad_id')->references('id')->on('comunidades')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('ocupacion_id')->references('id')->on('tipo_ocupaciones')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('tipo_inmueble_id')->references('id')->on('tipo_inmuebles')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inmuebles');
    }
};

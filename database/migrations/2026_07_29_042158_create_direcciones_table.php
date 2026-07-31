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
        Schema::create('direcciones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('direccion_l9_id')->nullable()->index();
            $table->string('direccionable_type');
            $table->unsignedBigInteger('direccionable_id');
            $table->unsignedBigInteger('tipo_direccion_id');
            $table->string('direccion1', 100)->nullable();
            $table->unsignedBigInteger('via_id')->nullable();
            $table->string('numero', 3)->nullable();
            $table->string('portal', 3)->nullable();
            $table->string('piso', 15)->nullable();
            $table->string('puerta', 4)->nullable();
            $table->string('barrio', 50)->nullable();
            $table->unsignedBigInteger('pais_id')->default(67);
            $table->string('codigo_postal', 10)->nullable();
            $table->unsignedBigInteger('provincia_id')->nullable();
            $table->unsignedBigInteger('municipio_id')->nullable();
            $table->unsignedBigInteger('poblacion_id')->nullable();
            $table->string('provincia', 30)->nullable();
            $table->string('municipio', 100)->nullable();
            $table->string('poblacion', 100)->nullable();
            $table->unsignedBigInteger('estado_id')->default(1);
            $table->timestamps();

            $table->index(['direccionable_type', 'direccionable_id']);

            $table->foreign('tipo_direccion_id')->references('id')->on('tipo_direcciones')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('via_id')->references('id')->on('vias')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('pais_id')->references('id')->on('paises')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('provincia_id')->references('id')->on('provincias')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('municipio_id')->references('id')->on('municipios')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('poblacion_id')->references('id')->on('poblaciones')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('direcciones');
    }
};

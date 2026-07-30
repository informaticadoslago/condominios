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
        Schema::create('dosl_direcciones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('direccion_l9_id')->nullable()->index();
            $table->string('direccionable_type');
            $table->unsignedBigInteger('direccionable_id');
            $table->unsignedBigInteger('tipo_direccion_id')->index('dosl_direcciones_tipo_direccion_id_foreign');
            $table->string('direccion1', 100)->nullable();
            $table->unsignedBigInteger('via_id')->nullable()->index('dosl_direcciones_via_id_foreign');
            $table->string('numero', 3)->nullable();
            $table->string('portal', 3)->nullable();
            $table->string('piso', 15)->nullable();
            $table->string('puerta', 4)->nullable();
            $table->string('barrio', 50)->nullable();
            $table->unsignedBigInteger('pais_id')->default(67)->index('dosl_direcciones_pais_id_foreign');
            $table->string('codigo_postal', 10)->nullable();
            $table->unsignedBigInteger('provincia_id')->nullable()->index('dosl_direcciones_provincia_id_foreign');
            $table->unsignedBigInteger('municipio_id')->nullable()->index('dosl_direcciones_municipio_id_foreign');
            $table->unsignedBigInteger('poblacion_id')->nullable()->index('dosl_direcciones_poblacion_id_foreign');
            $table->string('provincia', 30)->nullable();
            $table->string('municipio', 100)->nullable();
            $table->string('poblacion', 100)->nullable();
            $table->unsignedBigInteger('estado_id')->default(1)->index('dosl_direcciones_estado_id_foreign');
            $table->timestamps();

            $table->index(['direccionable_type', 'direccionable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dosl_direcciones');
    }
};

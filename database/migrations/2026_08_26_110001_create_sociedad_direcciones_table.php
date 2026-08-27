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
        Schema::create('sociedad_direcciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sociedad_id')->index('sociedad_direcciones_sociedad_id_foreign');
            $table->string('direccion1', 150)->nullable();
            $table->string('numero', 10)->nullable();
            $table->string('piso', 10)->nullable();
            $table->string('puerta', 10)->nullable();
            $table->string('codigo_postal', 10)->nullable();
            $table->unsignedBigInteger('provincia_id')->nullable()->index('sociedad_direcciones_provincia_id_foreign');
            $table->unsignedBigInteger('municipio_id')->nullable()->index('sociedad_direcciones_municipio_id_foreign');
            // Sede / Domicilio social. Con histórico vía ConHistorialEstado: cada cambio
            // (incluido el alta) queda en historial_estados. Solo puede haber una fila con
            // estado_id = DOMICILIO_SOCIAL por sociedad; lo garantiza el modelo, no la BD.
            $table->unsignedBigInteger('estado_id')->default(1)->index('sociedad_direcciones_estado_id_foreign');
            // Independiente del estado anterior: una sede (o el propio domicilio social)
            // puede ser además centro de trabajo.
            $table->boolean('es_centro_trabajo')->default(false);
            $table->timestamps();

            $table->foreign('sociedad_id')->references('id')->on('sociedades')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('provincia_id')->references('id')->on('provincias')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('municipio_id')->references('id')->on('municipios')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('estado_id')->references('id')->on('estado_direccion_sociedades')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sociedad_direcciones');
    }
};

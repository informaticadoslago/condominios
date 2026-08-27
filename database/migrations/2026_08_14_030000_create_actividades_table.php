<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una actividad separa, dentro de una misma comunidad y un mismo CIF, dos gestiones
     * con presupuesto propio (dos torres que comparten patio, por ejemplo). Cada
     * actividad es, en la contabilidad, un proyecto: ver EnlaceContableActividad.
     */
    public function up(): void
    {
        Schema::create('actividades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comunidad_id')->index('actividades_comunidad_id_foreign');
            $table->string('nombre', 100);

            // Id del proyecto contable, tal y como lo devuelve la contabilidad al
            // enlazarla (ver EnlaceContableActividad). A propósito SIN clave ajena: la
            // gestión es un cliente más de la contabilidad, igual que un sistema ajeno
            // entrando por la API, y un cliente no ata la base de datos del servicio del
            // que tira. Nulo si la comunidad todavía no lleva contabilidad.
            $table->unsignedBigInteger('proyecto_contable_id')->nullable();

            $table->timestamps();

            $table->foreign('comunidad_id')->references('id')->on('comunidades')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actividades');
    }
};

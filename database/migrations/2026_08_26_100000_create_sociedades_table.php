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
        Schema::create('sociedades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('persona_id')->unique()->index('sociedades_persona_id_foreign');
            $table->unsignedBigInteger('estado_id')->default(1)->index('sociedades_estado_id_foreign');
            // Empresa contable en la que esta sociedad lleva sus libros. Se rellena con
            // el botón "Enlace contabilidad".
            //
            // A propósito SIN clave ajena: la gestión es un cliente más de la
            // contabilidad, igual que lo sería un sistema ajeno entrando por la API, y
            // un cliente no ata la base de datos del servicio del que tira. La
            // integridad la garantiza el servicio que rellena la columna, no el motor.
            $table->unsignedBigInteger('empresa_contable_id')->nullable();
            $table->timestamps();

            $table->foreign('persona_id')->references('id')->on('personas')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sociedades');
    }
};

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
            $table->unsignedBigInteger('persona_id')->nullable()->index('direcciones_persona_id_foreign');
            $table->unsignedInteger('tipo_direccion_id')->default(1)->index('direcciones_tipo_direccion_id_foreign');
            $table->unsignedBigInteger('via_id')->nullable()->index('direcciones_via_id_foreign');
            $table->string('numero', 3)->nullable();
            $table->string('portal', 3)->nullable();
            $table->string('piso', 15)->nullable();
            $table->string('puerta', 4)->nullable();
            $table->string('barrio', 50)->nullable();
            $table->unsignedInteger('poblacion_id')->nullable();
            $table->string('codigopostal', 15)->nullable();
            $table->boolean('estado')->default(true);
            $table->unsignedBigInteger('estado_id')->default(1)->index('direcciones_estado_id_foreign');
            $table->timestamps();
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

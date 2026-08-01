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
        Schema::create('presupuestos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comunidad_id')->index('presupuestos_comunidad_id_foreign');
            $table->string('nombre', 100);
            $table->unsignedSmallInteger('anho');
            $table->unsignedBigInteger('estado_id')->default(1)->index('presupuestos_estado_id_foreign');
            $table->timestamps();

            $table->foreign('comunidad_id')->references('id')->on('comunidades')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('estado_id')->references('id')->on('tipo_estado_presupuestos')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presupuestos');
    }
};

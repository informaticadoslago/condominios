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
        Schema::create('sociedad_proveedores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sociedad_id')->index('sociedad_proveedores_sociedad_id_foreign');
            $table->unsignedBigInteger('persona_sociedad_id')->index('sociedad_proveedores_persona_sociedad_id_foreign');
            $table->unsignedBigInteger('estado_id')->default(1)->index('sociedad_proveedores_estado_id_foreign');
            $table->timestamps();

            $table->unique(['sociedad_id', 'persona_sociedad_id']);
            $table->foreign('sociedad_id')->references('id')->on('sociedades')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('persona_sociedad_id')->references('id')->on('personas_sociedad')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sociedad_proveedores');
    }
};

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
        Schema::create('grupos_de_reparto', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comunidad_id')->index('grupos_de_reparto_comunidad_id_foreign');
            $table->string('nombre', 100);
            $table->timestamps();

            $table->foreign('comunidad_id')->references('id')->on('comunidades')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupos_de_reparto');
    }
};

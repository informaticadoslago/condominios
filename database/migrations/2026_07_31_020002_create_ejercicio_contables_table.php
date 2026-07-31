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
        Schema::create('ejercicio_contables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comunidad_id')->index('ejercicio_contables_comunidad_id_foreign');
            $table->string('nombre', 50);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->boolean('cerrado')->default(false);
            $table->timestamps();

            $table->unique(['comunidad_id', 'nombre']);
            $table->foreign('comunidad_id')->references('id')->on('comunidades')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ejercicio_contables');
    }
};

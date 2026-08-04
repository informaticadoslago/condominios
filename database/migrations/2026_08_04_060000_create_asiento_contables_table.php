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
        Schema::create('asiento_contables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ejercicio_contable_id')->index('asiento_contables_ejercicio_contable_id_foreign');
            $table->unsignedInteger('numero');
            $table->date('fecha');
            $table->string('concepto', 255);
            $table->timestamps();

            $table->unique(['ejercicio_contable_id', 'numero']);

            $table->foreign('ejercicio_contable_id')->references('id')->on('ejercicio_contables')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asiento_contables');
    }
};

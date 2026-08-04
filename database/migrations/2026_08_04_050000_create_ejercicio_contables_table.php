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
            $table->unsignedBigInteger('empresa_contable_id')->index('ejercicio_contables_empresa_contable_id_foreign');
            $table->string('nombre', 50);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->boolean('cerrado')->default(false);
            $table->timestamps();

            $table->unique(['empresa_contable_id', 'nombre']);

            $table->foreign('empresa_contable_id')->references('id')->on('empresas_contables')->onUpdate('restrict')->onDelete('restrict');
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

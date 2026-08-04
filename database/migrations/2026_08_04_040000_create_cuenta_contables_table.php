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
        Schema::create('cuenta_contables', function (Blueprint $table) {
            $table->id();
            // Nulo = cuenta maestra, aún no asignada a ninguna empresa.
            $table->unsignedBigInteger('empresa_contable_id')->nullable()->index('cuenta_contables_empresa_contable_id_foreign');
            $table->unsignedBigInteger('tipo_cuenta_contable_id')->index('cuenta_contables_tipo_cuenta_contable_id_foreign');
            $table->unsignedBigInteger('cuenta_padre_id')->nullable()->index('cuenta_contables_cuenta_padre_id_foreign');
            $table->string('codigo', 8);
            $table->string('nombre', 150);
            $table->unsignedBigInteger('estado_id')->default(1)->index('cuenta_contables_estado_id_foreign');
            $table->timestamps();

            $table->unique(['empresa_contable_id', 'codigo']);

            $table->foreign('empresa_contable_id')->references('id')->on('empresas_contables')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('tipo_cuenta_contable_id')->references('id')->on('tipo_cuenta_contables')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('cuenta_padre_id')->references('id')->on('cuenta_contables')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuenta_contables');
    }
};

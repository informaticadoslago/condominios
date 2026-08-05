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
        Schema::create('tercero_contables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_contable_id')->index('tercero_contables_empresa_contable_id_foreign');
            $table->unsignedBigInteger('tipo_tercero_contable_id')->index('tercero_contables_tipo_tercero_contable_id_foreign');

            // Cómo se refiere a este tercero quien manda los asientos. Par opaco: la
            // contabilidad guarda las dos cadenas y las compara, pero no las interpreta
            // ni tiene FK contra ninguna tabla de fuera. Nulos si el tercero se dio de
            // alta a mano dentro de la propia contabilidad.
            $table->string('sujeto_tipo', 50)->nullable();
            $table->string('sujeto_id', 100)->nullable();

            // Datos fiscales propios de la contabilidad, para libros registro,
            // certificados de retenciones y el 347. No se consultan a nadie: los manda
            // quien da de alta al tercero.
            $table->string('nif', 20)->nullable();
            $table->string('razon_social', 150);

            $table->unsignedBigInteger('cuenta_contable_id')->unique();
            $table->unsignedBigInteger('estado_id')->default(1)->index('tercero_contables_estado_id_foreign');
            $table->timestamps();

            $table->unique(['empresa_contable_id', 'sujeto_tipo', 'sujeto_id'], 'tercero_contables_sujeto_unique');
            $table->index(['empresa_contable_id', 'nif'], 'tercero_contables_nif_index');

            $table->foreign('empresa_contable_id')->references('id')->on('empresas_contables')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('tipo_tercero_contable_id')->references('id')->on('tipo_tercero_contables')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('cuenta_contable_id')->references('id')->on('cuenta_contables')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('estado_id')->references('id')->on('estados')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tercero_contables');
    }
};

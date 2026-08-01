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
        Schema::create('campos_plantillas_facturas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plantilla_factura_id')->index('campos_plantillas_facturas_plantilla_factura_id_foreign');
            $table->unsignedBigInteger('tipo_campo_plantilla_factura_id')->index('campos_plantillas_tipo_campo_id_foreign');
            $table->string('texto_ancla', 150);
            $table->string('valor_ejemplo', 100)->nullable();
            $table->timestamps();

            $table->foreign('plantilla_factura_id')->references('id')->on('plantillas_facturas')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign('tipo_campo_plantilla_factura_id', 'campos_plantillas_tipo_campo_id_foreign')->references('id')->on('tipo_campo_plantilla_facturas')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campos_plantillas_facturas');
    }
};

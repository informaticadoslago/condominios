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
        Schema::create('facturas_proveedores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('documento_id')->unique('facturas_proveedores_documento_id_unique');
            $table->unsignedBigInteger('proveedor_id')->index('facturas_proveedores_proveedor_id_foreign');
            $table->string('numero_factura', 60)->nullable();
            $table->string('fecha_factura', 20)->nullable();
            $table->decimal('importe', 10, 2)->nullable();
            $table->timestamps();

            $table->foreign('documento_id')->references('id')->on('documentos')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign('proveedor_id')->references('id')->on('proveedores')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facturas_proveedores');
    }
};

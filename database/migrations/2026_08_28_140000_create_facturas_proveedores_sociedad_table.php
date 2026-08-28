<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equivalente a facturas_proveedores (comunidad), pero sociedad sí desglosa IVA: en vez
 * de un único importe, guarda base y total (las cuotas van en su propia tabla, ver
 * cuotas_iva_facturas_proveedores_sociedad). Igual que en comunidad, esto es solo el alta
 * administrativa; la contabilización (con el IVA soportado a la 472) es un paso posterior
 * y explícito, todavía sin construir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturas_proveedores_sociedad', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('documento_id')->nullable()->unique('facturas_proveedores_sociedad_documento_id_unique');
            $table->unsignedBigInteger('proveedor_id')->index('facturas_proveedores_sociedad_proveedor_id_foreign');
            $table->string('numero_factura', 60)->nullable();
            $table->string('fecha_factura', 20)->nullable();
            $table->decimal('importe_base', 10, 2)->nullable();
            $table->decimal('importe_total', 10, 2)->nullable();
            $table->timestamps();

            $table->foreign('documento_id')->references('id')->on('documentos')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign('proveedor_id')->references('id')->on('proveedores')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas_proveedores_sociedad');
    }
};

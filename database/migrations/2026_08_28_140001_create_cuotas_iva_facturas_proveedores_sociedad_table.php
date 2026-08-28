<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Una factura de sociedad puede traer varias cuotas de IVA (21/10/4/0%), no siempre todas. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuotas_iva_facturas_proveedores_sociedad', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('factura_proveedor_sociedad_id')->index('cuotas_iva_facturas_prov_sociedad_factura_id_foreign');
            $table->decimal('tipo_iva', 5, 2);
            $table->decimal('importe', 10, 2);
            $table->timestamps();

            $table->foreign('factura_proveedor_sociedad_id', 'cuotas_iva_facturas_prov_sociedad_factura_id_foreign')
                ->references('id')->on('facturas_proveedores_sociedad')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuotas_iva_facturas_proveedores_sociedad');
    }
};

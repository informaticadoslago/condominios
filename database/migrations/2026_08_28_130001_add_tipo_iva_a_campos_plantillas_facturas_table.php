<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una plantilla puede tener varias filas de tipo CUOTA_IVA (una por cada % de IVA que
 * use ese proveedor: 21/10/4/0...), así que necesitan algo que las distinga entre sí
 * además del tipo de campo. No todas las facturas de un proveedor traen todas: las que
 * falten en el texto de una factura concreta simplemente no se localizan y no cuentan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campos_plantillas_facturas', function (Blueprint $table) {
            $table->decimal('tipo_iva', 5, 2)->nullable()->after('tipo_campo_plantilla_factura_id');
        });
    }

    public function down(): void
    {
        Schema::table('campos_plantillas_facturas', function (Blueprint $table) {
            $table->dropColumn('tipo_iva');
        });
    }
};

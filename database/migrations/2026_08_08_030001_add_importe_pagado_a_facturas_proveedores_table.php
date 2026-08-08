<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas_proveedores', function (Blueprint $table) {
            // Suma de los pagos de esta factura. Es un total al vuelo, no un dato aparte:
            // lo escribe RegistrarPagoFactura cada vez que entra un pago, y sirve para
            // saber lo que queda pendiente sin sumar la tabla entera en cada listado.
            $table->decimal('importe_pagado', 10, 2)->default(0)->after('importe');
        });
    }

    public function down(): void
    {
        Schema::table('facturas_proveedores', function (Blueprint $table) {
            $table->dropColumn('importe_pagado');
        });
    }
};

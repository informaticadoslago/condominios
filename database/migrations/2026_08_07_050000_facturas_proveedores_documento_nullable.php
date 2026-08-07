<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una factura puede vivir sin papel: se teclea (o se lee su QR de VeriFactu) y queda
     * como «sin soporte» hasta que aparezca el PDF, si es que aparece.
     *
     * El índice único se queda: MySQL admite tantos NULL como haga falta en un único, así
     * que sigue impidiendo que dos facturas compartan el mismo documento.
     */
    public function up(): void
    {
        Schema::table('facturas_proveedores', function (Blueprint $table) {
            $table->unsignedBigInteger('documento_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('facturas_proveedores', function (Blueprint $table) {
            $table->unsignedBigInteger('documento_id')->nullable(false)->change();
        });
    }
};

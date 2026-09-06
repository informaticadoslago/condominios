<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobros', function (Blueprint $table) {
            // Solo tiene sentido tecleado a mano en Compensación: el resto de formas de
            // pago ya se explican solas (transferencia, remesa...). Es lo que
            // EnlazarCobrosContabilidad pone en la línea de contrapartida del asiento.
            $table->string('concepto')->nullable()->after('importe');
        });
    }

    public function down(): void
    {
        Schema::table('cobros', function (Blueprint $table) {
            $table->dropColumn('concepto');
        });
    }
};

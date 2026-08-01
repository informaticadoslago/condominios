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
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->unsignedTinyInteger('numero_pagos')->nullable()->after('anho');
            $table->date('fecha_primer_pago')->nullable()->after('numero_pagos');
            $table->unsignedBigInteger('periodicidad_id')->nullable()->after('fecha_primer_pago')
                ->index('presupuestos_periodicidad_id_foreign');

            $table->foreign('periodicidad_id')->references('id')->on('tipo_periodicidad_pagos')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->dropForeign(['periodicidad_id']);
            $table->dropColumn(['numero_pagos', 'fecha_primer_pago', 'periodicidad_id']);
        });
    }
};

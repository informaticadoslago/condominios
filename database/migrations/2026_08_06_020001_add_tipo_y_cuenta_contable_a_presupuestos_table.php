<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            // Los que ya existen son de cuotas: la derrama es lo excepcional.
            $table->unsignedBigInteger('tipo_presupuesto_id')->default(1)->after('anho')
                ->index('presupuestos_tipo_presupuesto_id_foreign');

            // Subcuenta de ingresos de ESTE presupuesto (75000001, 75010002…), tal y como
            // la devuelve la contabilidad al aprobarlo. Texto opaco, no una FK: la
            // contabilidad no conoce a la gestión y la flecha va en un solo sentido.
            $table->char('cuenta_contable', 8)->nullable()->after('tipo_presupuesto_id');

            $table->foreign('tipo_presupuesto_id')->references('id')->on('tipo_presupuestos')->onUpdate('restrict')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->dropForeign(['tipo_presupuesto_id']);
            $table->dropColumn(['tipo_presupuesto_id', 'cuenta_contable']);
        });
    }
};

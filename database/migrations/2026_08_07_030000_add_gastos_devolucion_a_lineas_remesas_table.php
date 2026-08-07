<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lineas_remesas', function (Blueprint $table) {
            // Comisión que cobró el banco por devolver este adeudo, tal y como se teclea al
            // registrar la tanda. Se repercute al propietario, así que aquí queda el detalle
            // de cuánto se le cargó por esta devolución en concreto; el acumulado que decide
            // lo que debe vive en el recibo.
            //
            // El banco a veces carga las comisiones juntas en un solo apunte: entonces se
            // teclea el importe unitario y la suma de todas cuadra con ese cargo.
            $table->decimal('gastos_devolucion', 12, 2)->default(0)->after('motivo_devolucion');
        });
    }

    public function down(): void
    {
        Schema::table('lineas_remesas', function (Blueprint $table) {
            $table->dropColumn('gastos_devolucion');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Forma de pago para saldar un recibo con el saldo a favor de su propio propietario
 * (ver RegistrarCobro::aplicarSaldoAFavor), sin que entre dinero nuevo por banco.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('formas_de_pago')->insertOrIgnore([
            'descripcion' => 'Compensación',
            'estado_id'   => 1,
        ]);
    }

    public function down(): void
    {
        DB::table('formas_de_pago')->where('descripcion', 'Compensación')->delete();
    }
};

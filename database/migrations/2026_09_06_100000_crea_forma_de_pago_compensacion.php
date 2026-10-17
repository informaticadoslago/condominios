<?php

use App\Models\FormaDePago;
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
        // Con su id fijo (el de la constante): en una base nueva esta migración corre antes
        // que FormasDePagoSeeder, y sin id se quedaría con el 1, el de Recibo bancario.
        DB::table('formas_de_pago')->insertOrIgnore([
            'id'          => FormaDePago::COMPENSACION,
            'descripcion' => 'Compensación',
            'estado_id'   => 1,
        ]);
    }

    public function down(): void
    {
        DB::table('formas_de_pago')->where('descripcion', 'Compensación')->delete();
    }
};

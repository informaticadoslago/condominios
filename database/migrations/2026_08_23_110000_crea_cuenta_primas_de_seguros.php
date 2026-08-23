<?php

use App\Models\CuentaContable;
use App\Models\TipoCuentaContable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Faltaba la cuenta 62500000 "Primas de seguros" en el plan de cuentas global: existía
 * la subcuenta 62500001 (SEGURO EDIFICIO) pero no la cuenta de la que debía colgar.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('cuenta_contables')->insertOrIgnore([
            'empresa_contable_id'     => null,
            'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO,
            'codigo'                  => '62500000',
            'nombre'                  => 'Primas de seguros',
            'estado_id'               => 1,
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);

        CuentaContable::recolgarPlan(null);
    }

    public function down(): void
    {
        DB::table('cuenta_contables')
            ->whereNull('empresa_contable_id')
            ->where('codigo', '62500000')
            ->delete();

        CuentaContable::recolgarPlan(null);
    }
};

<?php

use App\Models\CuentaContable;
use App\Models\EmpresaContable;
use Database\Seeders\PlanCuentasComunidadesSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Segunda vuelta de la anterior, ahora que el tercer nivel del PGC —la cuenta de 3
 * cifras— también entra en el plan: 7500 (cuotas) y 7501 (derramas) son hermanas dentro
 * del 750, no una colgando de la otra.
 *
 * Va aparte y no dentro de la anterior porque esa ya está pasada donde estaba pasada.
 * Sobre una base que aún no tenga ninguna de las dos, esta no hace más que repetir un
 * trabajo ya hecho, que es idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! CuentaContable::whereNull('empresa_contable_id')->exists()) {
            return;
        }

        (new PlanCuentasComunidadesSeeder())->run();

        foreach (EmpresaContable::pluck('id') as $empresaContableId) {
            $suyos = CuentaContable::deEmpresa($empresaContableId)->pluck('codigo')->all();

            foreach (CuentaContable::whereNull('empresa_contable_id')->get() as $maestra) {
                if ($maestra->esAgrupacion() && ! in_array($maestra->codigo, $suyos, true)) {
                    CuentaContable::create([
                        'empresa_contable_id'     => $empresaContableId,
                        'tipo_cuenta_contable_id' => $maestra->tipo_cuenta_contable_id,
                        'codigo'                  => $maestra->codigo,
                        'nombre'                  => $maestra->nombre,
                        'estado_id'               => $maestra->estado_id,
                    ]);
                }
            }

            CuentaContable::recolgarPlan($empresaContableId);
        }
    }

    public function down(): void
    {
        // Sin vuelta atrás, igual que la anterior.
    }
};

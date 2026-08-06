<?php

use App\Models\CuentaContable;
use App\Models\EmpresaContable;
use Database\Seeders\PlanCuentasComunidadesSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Mete en el plan los grupos y subgrupos del PGC y recoloca lo que ya había colgando de
 * ellos: hasta ahora el padre era siempre los 4 primeros dígitos + "0000", así que
 * 12100000 y 12900000 salían colgando de 12000000 cuando en realidad son hermanas suyas
 * dentro del subgrupo 12.
 *
 * No toca ningún dato contable: añade filas de agrupación y cambia cuenta_padre_id, que
 * es solo de dónde cuelga cada cuenta en el árbol de la pantalla.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sobre una base recién creada no hay nada que recolocar, y sembrar aquí el plan
        // le cambiaría el punto de partida a quien monta la base desde cero (los tests,
        // sin ir más lejos, que siembran ellos lo que necesitan).
        if (! CuentaContable::whereNull('empresa_contable_id')->exists()) {
            return;
        }

        // La plantilla, que es de donde salen los grupos y subgrupos. Va con
        // firstOrCreate, así que sobre un plan ya sembrado solo añade lo que falta.
        (new PlanCuentasComunidadesSeeder())->run();

        // Y cada empresa contable se lleva las agrupaciones que le falten: su plan es una
        // copia del maestro hecha el día que se dio de alta, anterior a todo esto.
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
        // Sin vuelta atrás: la jerarquía anterior no se puede recalcular más que con la
        // regla vieja, y esa regla ya no existe en el código. Las agrupaciones tampoco se
        // borran solas: si alguien ha apuntado debajo, se llevaría por delante su rama.
    }
};

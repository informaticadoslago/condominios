<?php

namespace Database\Seeders;

use App\Models\CuentaContablePlantilla;
use App\Models\TipoCuentaContable;
use Illuminate\Database\Seeder;

class PlanCuentasComunidadesSeeder extends Seeder
{
    /**
     * Plantilla de plan de cuentas para comunidades de propietarios: lo que se añade
     * encima de la común (PlanCuentasBaseSeeder) al enlazar una comunidad con la
     * contabilidad. Cuentas maestras (empresa_contable_id nulo, plantilla 'comunidad').
     *
     * Es *una* plantilla, no el plan de cuentas del sistema. La contabilidad es genérica y
     * no sabe qué es una cuota ni una derrama: solo mueve céntimos entre códigos.
     *
     * El criterio con el que se eligen los códigos está en docs/plan-de-cuentas.md.
     */
    public function run(): void
    {
        $agrupaciones = [
            ['codigo' => '7',  'nombre' => 'Ventas e ingresos'],
            ['codigo' => '75', 'nombre' => 'Otros ingresos de gestión'],
            // El 750 lo deja libre el PGC; aquí agrupa lo que cobra la comunidad, que se
            // desglosa en las cuentas 7500 (cuotas) y 7501 (derramas).
            ['codigo' => '750', 'nombre' => 'Ingresos de la comunidad'],
        ];

        $cuentas = [
            // Mismo código que la «Clientes» común (430): una comunidad no tiene
            // clientes, pero la 430 es la cuenta de los deudores por la actividad
            // ordinaria, y eso es exactamente un propietario (ver docs/plan-de-cuentas.md,
            // «Los dos criterios»). Al copiar esta plantilla, pisa el nombre de la común.
            ['codigo' => '43000000', 'nombre' => 'Propietarios', 'tipo_cuenta_contable_id' => TipoCuentaContable::ACTIVO],
            // Subgrupo 75 «Otros ingresos de gestión»: el PGC llega hasta 3 dígitos y deja
            // libre el 750, así que las cuotas y las derramas se abren ahí con el 4.º
            // dígito, que es nuestro: son las cuentas 7500 y 7501, hermanas dentro del
            // 750, no una colgando de la otra. Ver docs/plan-de-cuentas.md.
            ['codigo' => '75000000', 'nombre' => 'Ingresos por cuotas de comunidad', 'tipo_cuenta_contable_id' => TipoCuentaContable::INGRESO],
            ['codigo' => '75010000', 'nombre' => 'Ingresos por derramas', 'tipo_cuenta_contable_id' => TipoCuentaContable::INGRESO],
        ];

        foreach ([...$agrupaciones, ...$cuentas] as $cuenta) {
            CuentaContablePlantilla::firstOrCreate(
                ['codigo' => $cuenta['codigo'], 'plantilla' => CuentaContablePlantilla::PLANTILLA_COMUNIDAD],
                $cuenta
            );
        }

        CuentaContablePlantilla::recolgarPlan(CuentaContablePlantilla::PLANTILLA_COMUNIDAD);
    }
}

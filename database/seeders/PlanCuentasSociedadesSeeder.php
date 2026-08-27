<?php

namespace Database\Seeders;

use App\Models\CuentaContablePlantilla;
use App\Models\TipoCuentaContable;
use Illuminate\Database\Seeder;

class PlanCuentasSociedadesSeeder extends Seeder
{
    /**
     * Plantilla de plan de cuentas para sociedades que facturan con IVA: lo que se añade
     * encima de la común (PlanCuentasBaseSeeder) al enlazar una sociedad con la
     * contabilidad. Cuentas maestras (empresa_contable_id nulo, plantilla 'sociedad').
     *
     * El criterio con el que se eligen los códigos está en docs/plan-de-cuentas.md.
     */
    public function run(): void
    {
        $agrupaciones = [
            ['codigo' => '47',  'nombre' => 'Administraciones públicas'],
            ['codigo' => '472', 'nombre' => 'Hacienda Pública, IVA soportado'],
            ['codigo' => '477', 'nombre' => 'Hacienda Pública, IVA repercutido'],
            ['codigo' => '60',  'nombre' => 'Compras'],
            ['codigo' => '70',  'nombre' => 'Ventas de mercaderías, de producción propia, de servicios, etc.'],
        ];

        $cuentas = [
            // Deudor frente a Hacienda: el IVA que la sociedad paga en sus compras y se
            // puede deducir.
            ['codigo' => '47200000', 'nombre' => 'H.P., IVA soportado', 'tipo_cuenta_contable_id' => TipoCuentaContable::ACTIVO],
            // Acreedor frente a Hacienda: el IVA que la sociedad repercute en sus
            // facturas de venta y tiene que ingresar.
            ['codigo' => '47700000', 'nombre' => 'H.P., IVA repercutido', 'tipo_cuenta_contable_id' => TipoCuentaContable::PASIVO],
            ['codigo' => '60000000', 'nombre' => 'Compras', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            ['codigo' => '70000000', 'nombre' => 'Ventas / prestación de servicios', 'tipo_cuenta_contable_id' => TipoCuentaContable::INGRESO],
        ];

        foreach ([...$agrupaciones, ...$cuentas] as $cuenta) {
            CuentaContablePlantilla::firstOrCreate(
                ['codigo' => $cuenta['codigo'], 'plantilla' => CuentaContablePlantilla::PLANTILLA_SOCIEDAD],
                $cuenta
            );
        }

        CuentaContablePlantilla::recolgarPlan(CuentaContablePlantilla::PLANTILLA_SOCIEDAD);
    }
}

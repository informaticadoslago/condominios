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
            // 62 «Servicios exteriores» ya vive en la base (PlanCuentasBaseSeeder): las
            // cuentas de aquí cuelgan de esa, no hace falta repetirla.
            ['codigo' => '63',  'nombre' => 'Tributos'],
            ['codigo' => '70',  'nombre' => 'Ventas de mercaderías, de producción propia, de servicios, etc.'],
        ];

        $cuentas = [
            // Deudor frente a Hacienda: el IVA que la sociedad paga en sus compras y se
            // puede deducir.
            ['codigo' => '47200000', 'nombre' => 'H.P., IVA soportado', 'tipo_cuenta_contable_id' => TipoCuentaContable::ACTIVO],
            // Acreedor frente a Hacienda: el IVA que la sociedad repercute en sus
            // facturas de venta y tiene que ingresar.
            ['codigo' => '47700000', 'nombre' => 'H.P., IVA repercutido', 'tipo_cuenta_contable_id' => TipoCuentaContable::PASIVO],
            // Subgrupo 60 «Compras»: el tipo de proveedor que compra mercaderías/materias
            // primas (va a 400) elige una de estas, en vez de una cuenta de gasto de la 62.
            ['codigo' => '60000000', 'nombre' => 'Compras de mercaderías', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            ['codigo' => '60100000', 'nombre' => 'Compras de materias primas', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            ['codigo' => '60200000', 'nombre' => 'Compras de otros aprovisionamientos', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            ['codigo' => '60700000', 'nombre' => 'Trabajos realizados por otras empresas', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            // Subgrupo 62 que falta en la base (622/623/625/626/628/629 ya están ahí y se
            // heredan solas): estas tres son igual de genéricas, pero el origen de esta
            // necesidad es de sociedad, así que viven aquí y no en la base.
            ['codigo' => '62100000', 'nombre' => 'Arrendamientos y cánones', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            ['codigo' => '62400000', 'nombre' => 'Transportes', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            ['codigo' => '62700000', 'nombre' => 'Publicidad, propaganda y relaciones públicas', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            // 630/634/636/638 son de la liquidación del Impuesto de Sociedades, no de
            // facturas de proveedor: no encajan aquí. La 631 sí (tasas, IBI, IAE...).
            ['codigo' => '63100000', 'nombre' => 'Otros tributos', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
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

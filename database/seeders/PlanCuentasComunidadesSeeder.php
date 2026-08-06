<?php

namespace Database\Seeders;

use App\Models\CuentaContable;
use App\Models\TipoCuentaContable;
use Illuminate\Database\Seeder;

class PlanCuentasComunidadesSeeder extends Seeder
{
    /**
     * Plantilla de plan de cuentas para comunidades de propietarios: cuentas maestras
     * (empresa_contable_id nulo, sin asignar todavía) con las que arranca una empresa
     * contable nueva.
     *
     * Es *una* plantilla, no el plan de cuentas del sistema. La contabilidad es genérica y
     * no sabe qué es una cuota ni una derrama: solo mueve céntimos entre códigos. Para otro
     * tipo de empresa se añade otra plantilla al lado, no se toca esta.
     *
     * El criterio con el que se eligen los códigos está en docs/plan-de-cuentas.md.
     */
    public function run(): void
    {
        $cuentas = [
            // 120, del subgrupo 12 «Resultados pendientes de aplicación». El grupo 3 es
            // Existencias y no pintaba nada aquí.
            ['codigo' => '12000000', 'nombre' => 'Remanente', 'tipo_cuenta_contable_id' => TipoCuentaContable::PATRIMONIO_NETO],
            ['codigo' => '12100000', 'nombre' => 'Resultados negativos de ejercicios anteriores', 'tipo_cuenta_contable_id' => TipoCuentaContable::PATRIMONIO_NETO],
            // Donde aterriza al cierre la diferencia entre las 75xx y las 62xx.
            ['codigo' => '12900000', 'nombre' => 'Resultado del ejercicio', 'tipo_cuenta_contable_id' => TipoCuentaContable::PATRIMONIO_NETO],
            ['codigo' => '40000000', 'nombre' => 'Proveedores', 'tipo_cuenta_contable_id' => TipoCuentaContable::PASIVO],
            // Cuenta de grupo de la clase de tercero «cliente»: de ella cuelgan las
            // subcuentas 43000001, 43000002… una por propietario. No se desglosa por
            // concepto; para eso están las cuentas de ingreso 7500 y 7501.
            ['codigo' => '43000000', 'nombre' => 'Propietarios', 'tipo_cuenta_contable_id' => TipoCuentaContable::ACTIVO],
            ['codigo' => '57200000', 'nombre' => 'Bancos', 'tipo_cuenta_contable_id' => TipoCuentaContable::ACTIVO],
            ['codigo' => '62200000', 'nombre' => 'Reparación y conservación', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            ['codigo' => '62800000', 'nombre' => 'Suministros', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            ['codigo' => '62900000', 'nombre' => 'Servicios de limpieza', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            // Subgrupo 75 «Otros ingresos de gestión»: el PGC llega hasta 3 dígitos y deja
            // libre el 750, así que las cuotas y las derramas se abren ahí con el 4.º
            // dígito, que es nuestro. Ver docs/plan-de-cuentas.md.
            ['codigo' => '75000000', 'nombre' => 'Ingresos por cuotas de comunidad', 'tipo_cuenta_contable_id' => TipoCuentaContable::INGRESO],
            ['codigo' => '75010000', 'nombre' => 'Ingresos por derramas', 'tipo_cuenta_contable_id' => TipoCuentaContable::INGRESO],
        ];

        foreach ($cuentas as $cuenta) {
            CuentaContable::firstOrCreate(['codigo' => $cuenta['codigo'], 'empresa_contable_id' => null], $cuenta);
        }
    }
}

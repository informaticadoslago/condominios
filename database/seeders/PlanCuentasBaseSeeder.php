<?php

namespace Database\Seeders;

use App\Models\CuentaContablePlantilla;
use App\Models\TipoCuentaContable;
use Illuminate\Database\Seeder;

class PlanCuentasBaseSeeder extends Seeder
{
    /**
     * Cuentas maestra (plantilla nula) comunes a cualquier empresa contable, sea cual sea
     * su origen: se copian siempre, y encima de ellas se copia -si la hay- la plantilla
     * propia del origen (ver PlanCuentasComunidadesSeeder, PlanCuentasSociedadesSeeder y
     * CuentaContable::copiarPlanGlobalA). Viven en CuentaContablePlantilla, no en
     * cuenta_contables: esta tabla nunca lleva cuentas reales de una empresa.
     *
     * El criterio con el que se eligen los códigos está en docs/plan-de-cuentas.md.
     */
    public function run(): void
    {
        $agrupaciones = [
            ['codigo' => '1',  'nombre' => 'Financiación básica'],
            ['codigo' => '12', 'nombre' => 'Resultados pendientes de aplicación'],
            ['codigo' => '4',  'nombre' => 'Acreedores y deudores por operaciones comerciales'],
            ['codigo' => '40', 'nombre' => 'Proveedores'],
            ['codigo' => '41', 'nombre' => 'Acreedores varios'],
            ['codigo' => '43', 'nombre' => 'Clientes'],
            ['codigo' => '5',  'nombre' => 'Cuentas financieras'],
            ['codigo' => '57', 'nombre' => 'Tesorería'],
            ['codigo' => '6',  'nombre' => 'Compras y gastos'],
            ['codigo' => '62', 'nombre' => 'Servicios exteriores'],
        ];

        $cuentas = [
            // 120, del subgrupo 12 «Resultados pendientes de aplicación». El grupo 3 es
            // Existencias y no pintaba nada aquí.
            ['codigo' => '12000000', 'nombre' => 'Remanente', 'tipo_cuenta_contable_id' => TipoCuentaContable::PATRIMONIO_NETO],
            ['codigo' => '12100000', 'nombre' => 'Resultados negativos de ejercicios anteriores', 'tipo_cuenta_contable_id' => TipoCuentaContable::PATRIMONIO_NETO],
            // Donde aterriza al cierre la diferencia entre ingresos y gastos.
            ['codigo' => '12900000', 'nombre' => 'Resultado del ejercicio', 'tipo_cuenta_contable_id' => TipoCuentaContable::PATRIMONIO_NETO],
            ['codigo' => '40000000', 'nombre' => 'Proveedores', 'tipo_cuenta_contable_id' => TipoCuentaContable::PASIVO],
            // Cuenta de grupo de la clase de tercero «acreedor»: quien factura servicios.
            // La 400 queda para compras (mercaderías y aprovisionamientos).
            ['codigo' => '41000000', 'nombre' => 'Acreedores por prestaciones de servicios', 'tipo_cuenta_contable_id' => TipoCuentaContable::PASIVO],
            // Cuenta de grupo de la clase de tercero «cliente»: de ella cuelgan las
            // subcuentas 43000001, 43000002… una por cliente/propietario. La plantilla de
            // comunidad la pisa con el nombre «Propietarios» (mismo código, mismo uso
            // PGC-430; el nombre no vincula, ver docs/plan-de-cuentas.md).
            ['codigo' => '43000000', 'nombre' => 'Clientes', 'tipo_cuenta_contable_id' => TipoCuentaContable::ACTIVO],
            ['codigo' => '57200000', 'nombre' => 'Bancos', 'tipo_cuenta_contable_id' => TipoCuentaContable::ACTIVO],
            ['codigo' => '62200000', 'nombre' => 'Reparación y conservación', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            // Honorarios de profesionales independientes (administrador, abogado,
            // arquitecto, asesor…), aplica a cualquier empresa.
            ['codigo' => '62300000', 'nombre' => 'Servicios de profesionales independientes', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            ['codigo' => '62500000', 'nombre' => 'Primas de seguros', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            // Comisiones y gastos que carga el banco. La 62600001 viene siempre creada: es
            // la que se asigna de fábrica a cuenta_gasto_comisiones_bancarias al enlazar
            // una comunidad nueva (ver Comunidades\Lista::ejecutarEnlace); una sociedad
            // también las paga, aunque hoy no tenga ese enlace automático.
            ['codigo' => '62600000', 'nombre' => 'Servicios bancarios', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            ['codigo' => '62600001', 'nombre' => 'Comisiones bancarias', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            // Gasto periódico de la cuenta, ajeno a las remesas (mantenimiento, custodia
            // de valores…). No se asigna a cuenta_gasto_comisiones_bancarias: esa sigue
            // siendo la 62600001.
            ['codigo' => '62600002', 'nombre' => 'Comisiones de mantenimiento y administración de cuenta', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            ['codigo' => '62800000', 'nombre' => 'Suministros', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            ['codigo' => '62900000', 'nombre' => 'Servicios de limpieza', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
        ];

        foreach ([...$agrupaciones, ...$cuentas] as $cuenta) {
            CuentaContablePlantilla::firstOrCreate(
                ['codigo' => $cuenta['codigo'], 'plantilla' => null],
                $cuenta
            );
        }

        CuentaContablePlantilla::recolgarPlan(null);
    }
}

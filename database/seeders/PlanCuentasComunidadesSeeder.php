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
        // Grupos (1 cifra) y subgrupos (2), con la denominación del PGC. No se apunta en
        // ellos: solo agrupan a las cuentas de 3 cifras, que entre sí son hermanas. Van
        // sin tipo porque no tienen naturaleza propia: del grupo 4 cuelgan clientes
        // (activo) y proveedores (pasivo).
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
            ['codigo' => '7',  'nombre' => 'Ventas e ingresos'],
            ['codigo' => '75', 'nombre' => 'Otros ingresos de gestión'],
            // El 750 lo deja libre el PGC; aquí agrupa lo que cobra la comunidad, que se
            // desglosa en las cuentas 7500 (cuotas) y 7501 (derramas).
            ['codigo' => '750', 'nombre' => 'Ingresos de la comunidad'],
        ];

        $cuentas = [
            // 120, del subgrupo 12 «Resultados pendientes de aplicación». El grupo 3 es
            // Existencias y no pintaba nada aquí.
            ['codigo' => '12000000', 'nombre' => 'Remanente', 'tipo_cuenta_contable_id' => TipoCuentaContable::PATRIMONIO_NETO],
            ['codigo' => '12100000', 'nombre' => 'Resultados negativos de ejercicios anteriores', 'tipo_cuenta_contable_id' => TipoCuentaContable::PATRIMONIO_NETO],
            // Donde aterriza al cierre la diferencia entre las 75xx y las 62xx.
            ['codigo' => '12900000', 'nombre' => 'Resultado del ejercicio', 'tipo_cuenta_contable_id' => TipoCuentaContable::PATRIMONIO_NETO],
            ['codigo' => '40000000', 'nombre' => 'Proveedores', 'tipo_cuenta_contable_id' => TipoCuentaContable::PASIVO],
            // Cuenta de grupo de la clase de tercero «acreedor»: quien factura servicios a
            // la comunidad, que es casi todo lo que le entra. La 400 queda para compras.
            ['codigo' => '41000000', 'nombre' => 'Acreedores por prestaciones de servicios', 'tipo_cuenta_contable_id' => TipoCuentaContable::PASIVO],
            // Cuenta de grupo de la clase de tercero «cliente»: de ella cuelgan las
            // subcuentas 43000001, 43000002… una por propietario. No se desglosa por
            // concepto; para eso están las cuentas de ingreso 7500 y 7501.
            ['codigo' => '43000000', 'nombre' => 'Propietarios', 'tipo_cuenta_contable_id' => TipoCuentaContable::ACTIVO],
            ['codigo' => '57200000', 'nombre' => 'Bancos', 'tipo_cuenta_contable_id' => TipoCuentaContable::ACTIVO],
            ['codigo' => '62200000', 'nombre' => 'Reparación y conservación', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            // Los honorarios del administrador de fincas, y cualquier otro profesional
            // que facture a la comunidad (abogado, arquitecto…).
            ['codigo' => '62300000', 'nombre' => 'Servicios de profesionales independientes', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            // Comisiones y gastos que carga el banco al liquidar una remesa. La de
            // devolución no entra aquí: se repercute al propietario, no es gasto propio.
            // La 62600001 viene siempre creada: es la que se asigna de fábrica a
            // cuenta_gasto_comisiones_bancarias al enlazar una comunidad nueva (ver
            // Comunidades\Lista::ejecutarEnlace).
            ['codigo' => '62500000', 'nombre' => 'Primas de seguros', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            ['codigo' => '62600000', 'nombre' => 'Servicios bancarios', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            ['codigo' => '62600001', 'nombre' => 'Comisiones bancarias', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            // Gasto periódico de la cuenta, ajeno a las remesas (mantenimiento, custodia
            // de valores…). No se asigna a cuenta_gasto_comisiones_bancarias: esa sigue
            // siendo la 62600001.
            ['codigo' => '62600002', 'nombre' => 'Comisiones de mantenimiento y administración de cuenta', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            ['codigo' => '62800000', 'nombre' => 'Suministros', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            ['codigo' => '62900000', 'nombre' => 'Servicios de limpieza', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            // Subgrupo 75 «Otros ingresos de gestión»: el PGC llega hasta 3 dígitos y deja
            // libre el 750, así que las cuotas y las derramas se abren ahí con el 4.º
            // dígito, que es nuestro: son las cuentas 7500 y 7501, hermanas dentro del
            // 750, no una colgando de la otra. Ver docs/plan-de-cuentas.md.
            ['codigo' => '75000000', 'nombre' => 'Ingresos por cuotas de comunidad', 'tipo_cuenta_contable_id' => TipoCuentaContable::INGRESO],
            ['codigo' => '75010000', 'nombre' => 'Ingresos por derramas', 'tipo_cuenta_contable_id' => TipoCuentaContable::INGRESO],
        ];

        foreach ([...$agrupaciones, ...$cuentas] as $cuenta) {
            CuentaContable::firstOrCreate(['codigo' => $cuenta['codigo'], 'empresa_contable_id' => null], $cuenta);
        }

        // Y cada una a colgar de la suya, ahora que ya están todas.
        CuentaContable::recolgarPlan(null);
    }
}

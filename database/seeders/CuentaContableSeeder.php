<?php

namespace Database\Seeders;

use App\Models\CuentaContable;
use App\Models\TipoCuentaContable;
use Illuminate\Database\Seeder;

class CuentaContableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cuentas = [
            ['codigo' => '30000000', 'nombre' => 'Remanente', 'tipo_cuenta_contable_id' => TipoCuentaContable::PATRIMONIO_NETO],
            ['codigo' => '40000000', 'nombre' => 'Proveedores', 'tipo_cuenta_contable_id' => TipoCuentaContable::PASIVO],
            ['codigo' => '43000000', 'nombre' => 'Propietarios deudores por cuotas', 'tipo_cuenta_contable_id' => TipoCuentaContable::ACTIVO],
            ['codigo' => '43100000', 'nombre' => 'Propietarios deudores por derramas', 'tipo_cuenta_contable_id' => TipoCuentaContable::ACTIVO],
            ['codigo' => '57200000', 'nombre' => 'Bancos', 'tipo_cuenta_contable_id' => TipoCuentaContable::ACTIVO],
            ['codigo' => '62200000', 'nombre' => 'Reparación y conservación', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            ['codigo' => '62800000', 'nombre' => 'Suministros', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            ['codigo' => '62900000', 'nombre' => 'Servicios de limpieza', 'tipo_cuenta_contable_id' => TipoCuentaContable::GASTO],
            ['codigo' => '70000000', 'nombre' => 'Ingresos por cuotas de comunidad', 'tipo_cuenta_contable_id' => TipoCuentaContable::INGRESO],
            ['codigo' => '70100000', 'nombre' => 'Ingresos por derramas', 'tipo_cuenta_contable_id' => TipoCuentaContable::INGRESO],
        ];

        foreach ($cuentas as $cuenta) {
            CuentaContable::firstOrCreate(['codigo' => $cuenta['codigo']], $cuenta);
        }
    }
}

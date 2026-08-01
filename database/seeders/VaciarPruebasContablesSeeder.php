<?php

namespace Database\Seeders;

use App\Models\ApunteContable;
use App\Models\AsientoContable;
use App\Models\CuentaContable;
use Illuminate\Database\Seeder;

/**
 * Solo para mientras se prueba el módulo contable: borra TODOS los asientos
 * (con sus apuntes) y las subcuentas creadas a mano durante las pruebas, dejando
 * intacto el plan de cuentas base (las cuentas de grupo de CuentaContableSeeder,
 * que no tienen padre) y los ejercicios/comunidades.
 *
 * Uso: php artisan db:seed --class=VaciarPruebasContablesSeeder
 */
class VaciarPruebasContablesSeeder extends Seeder
{
    public function run(): void
    {
        ApunteContable::query()->delete();
        AsientoContable::query()->delete();

        // Ninguna cuenta del seeder base tiene padre; todo lo que cuelgue de una
        // cuenta de grupo es una subcuenta creada a mano durante las pruebas.
        CuentaContable::whereNotNull('cuenta_padre_id')->delete();
    }
}

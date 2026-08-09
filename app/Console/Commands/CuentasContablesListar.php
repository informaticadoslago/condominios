<?php

namespace App\Console\Commands;

use App\Models\CuentaContable;
use Illuminate\Console\Command;

class CuentasContablesListar extends Command
{
    protected $signature = 'condominios:cuentas-contables-listar {empresa? : ID de la empresa contable opcional}';

    protected $description = 'Lista cuentas contables con id y nombre, una por línea.';

    public function handle(): int
    {
        $empresa = $this->argument('empresa');

        $query = CuentaContable::query()->orderBy('id');

        if ($empresa !== null) {
            $query->where('empresa_contable_id', (int) $empresa);
        }

        $cuentas = $query->get();

        if ($cuentas->isEmpty()) {
            $this->info($empresa !== null
                ? "No hay cuentas contables para la empresa #{$empresa}."
                : 'No hay cuentas contables registradas.');

            return 0;
        }

        foreach ($cuentas as $cuenta) {
            $this->line("{$cuenta->id} {$cuenta->nombre}");
        }

        return 0;
    }
}

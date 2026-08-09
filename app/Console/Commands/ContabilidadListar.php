<?php

namespace App\Console\Commands;

use App\Models\EmpresaContable;
use Illuminate\Console\Command;

class ContabilidadListar extends Command
{
    protected $signature = 'condominios:contabilidades-listar';

    protected $description = 'Lista empresas contables con id y nombre, una por línea.';

    protected $aliases = ['condominios:contabilidad-listar'];

    public function handle(): int
    {
        $empresas = EmpresaContable::orderBy('id')->get();

        if ($empresas->isEmpty()) {
            $this->info('No hay empresas contables registradas.');

            return 0;
        }

        foreach ($empresas as $empresa) {
            $nombre = $empresa->razon_social ?: $empresa->cif ?: "Empresa #{$empresa->id}";
            $this->line("{$empresa->id} {$nombre}");
        }

        return 0;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\EmpresaContable;
use App\Services\Contabilidad\ContabilidadExportador;
use Illuminate\Console\Command;

class ContabilidadExportar extends Command
{
    protected $signature = 'condominios:contabilidad-exportar {empresa : ID de la empresa contable}';

    protected $description = 'Exporta una empresa contable entera (cuentas, terceros, ejercicios, asientos y apuntes) a un .zip en storage/app/coms';

    public function handle(ContabilidadExportador $exportador)
    {
        $empresaContable = EmpresaContable::find($this->argument('empresa'));

        if (! $empresaContable) {
            $this->error("No existe la empresa contable #{$this->argument('empresa')}.");

            return 1;
        }

        $this->info("Exportando contabilidad de '{$empresaContable->razon_social}' ({$empresaContable->cif}, #{$empresaContable->id})...");

        $nombreZip = $exportador->exportar($empresaContable);

        $this->info("Exportación completada: storage/app/coms/{$nombreZip}");

        return 0;
    }
}

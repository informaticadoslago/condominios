<?php

namespace App\Console\Commands;

use App\Models\Comunidad;
use App\Services\Comunidades\ComunidadExportador;
use Illuminate\Console\Command;

class ComunidadExportar extends Command
{
    protected $signature = 'condominios:comunidad-exportar {comunidad : ID de la comunidad}';

    protected $description = 'Exporta todos los datos de una comunidad (BD + documentos adjuntos) a un .zip en storage/app/coms';

    public function handle(ComunidadExportador $exportador)
    {
        $comunidad = Comunidad::with('persona')->find($this->argument('comunidad'));

        if (! $comunidad) {
            $this->error("No existe la comunidad #{$this->argument('comunidad')}.");

            return 1;
        }

        $nombre = $comunidad->persona->razon_social ?? $comunidad->persona->nombre_comercial ?? "#{$comunidad->id}";

        $this->info("Exportando comunidad '{$nombre}' (#{$comunidad->id})...");

        $nombreZip = $exportador->exportar($comunidad);

        $this->info("Exportación completada: storage/app/coms/{$nombreZip}");

        return 0;
    }
}

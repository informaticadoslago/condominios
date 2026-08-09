<?php

namespace App\Console\Commands;

use App\Models\Comunidad;
use Illuminate\Console\Command;

class ComunidadListar extends Command
{
    protected $signature = 'condominios:comunidades-listar';

    protected $description = 'Lista comunidades con id y nombre, una por línea.';

    protected $aliases = ['condominios:comunidad-listar'];

    public function handle(): int
    {
        $comunidades = Comunidad::with('persona')
            ->orderBy('id')
            ->get();

        if ($comunidades->isEmpty()) {
            $this->info('No hay comunidades registradas.');

            return 0;
        }

        foreach ($comunidades as $comunidad) {
            $nombre = $comunidad->persona?->razon_social
                ?? $comunidad->persona?->nombre_comercial
                ?? $comunidad->persona?->documento_identificativo
                ?? "Comunidad #{$comunidad->id}";

            $this->line("{$comunidad->id} {$nombre}");
        }

        return 0;
    }
}

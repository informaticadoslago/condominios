<?php

namespace App\Console\Commands;

use App\Models\Comunidad;
use App\Services\Comunidades\ComunidadEliminador;
use Illuminate\Console\Command;

class ComunidadBorrar extends Command
{
    protected $signature = 'condominios:comunidad-borrar {comunidad : ID de la comunidad}';

    protected $description = 'Borra una comunidad y TODOS sus datos relacionados (inmuebles, propietarios, proveedores, presupuestos, documentos...). Irreversible.';

    public function handle(ComunidadEliminador $eliminador)
    {
        $comunidad = Comunidad::with('persona')->find($this->argument('comunidad'));

        if (! $comunidad) {
            $this->error("No existe la comunidad #{$this->argument('comunidad')}.");

            return 1;
        }

        $nombre = $comunidad->persona->razon_social ?? $comunidad->persona->nombre_comercial ?? "#{$comunidad->id}";

        $this->warn("Se va a BORRAR PERMANENTEMENTE la comunidad '{$nombre}' (#{$comunidad->id}) y todos sus datos:");
        $this->warn('inmuebles, propietarios, proveedores, cuentas bancarias, presupuestos y documentos/facturas adjuntas.');
        $this->warn('La contabilidad va por su lado: se borra con condominios:contabilidad-borrar.');
        $this->warn('No hay papelera ni vuelta atrás (no se usan soft deletes en este proyecto).');

        if (! $this->confirm('¿Continuar?', false)) {
            $this->info('Cancelado.');

            return 0;
        }

        $eliminador->eliminar($comunidad);

        $this->info("Comunidad '{$nombre}' borrada.");

        return 0;
    }
}

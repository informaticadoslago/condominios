<?php

namespace App\Console\Commands;

use App\Models\Comunidad;
use App\Models\EmpresaContable;
use App\Services\Contabilidad\ContabilidadEliminador;
use Illuminate\Console\Command;

class ContabilidadBorrar extends Command
{
    protected $signature = 'condominios:contabilidad-borrar {empresa : ID de la empresa contable}';

    protected $description = 'Borra una empresa contable y TODOS sus libros (cuentas, terceros, ejercicios, asientos, apuntes). Irreversible.';

    public function handle(ContabilidadEliminador $eliminador)
    {
        $empresaContable = EmpresaContable::find($this->argument('empresa'));

        if (! $empresaContable) {
            $this->error("No existe la empresa contable #{$this->argument('empresa')}.");

            return 1;
        }

        $this->warn("Se va a BORRAR PERMANENTEMENTE la contabilidad de '{$empresaContable->razon_social}' ({$empresaContable->cif}, #{$empresaContable->id}):");
        $this->warn('plan de cuentas, terceros, ejercicios, asientos y apuntes, más su rol de acceso y los tokens de API que solo valían para ella.');
        $this->warn('No hay papelera ni vuelta atrás (no se usan soft deletes en este proyecto).');

        // La gestión guarda a qué empresa contable lleva sus libros cada comunidad, y esa
        // columna no tiene clave ajena (la contabilidad no conoce a la gestión). Si la
        // empresa se va, ese enlace se queda apuntando a la nada: se avisa y se quita.
        $comunidades = Comunidad::with('persona')
            ->where('empresa_contable_id', $empresaContable->id)
            ->get();

        if ($comunidades->isNotEmpty()) {
            $this->newLine();
            $this->warn("Además, {$comunidades->count()} comunidad(es) llevan sus libros en esta empresa y se quedarán SIN contabilidad enlazada:");

            foreach ($comunidades as $comunidad) {
                $nombre = $comunidad->persona->razon_social ?? $comunidad->persona->nombre_comercial ?? "#{$comunidad->id}";
                $this->warn("  - {$nombre} (#{$comunidad->id})");
            }

            $this->warn('Sus datos de gestión (recibos, presupuestos, facturas) NO se tocan: solo se les quita el enlace.');
        }

        $this->newLine();

        if (! $this->confirm('¿Continuar?', false)) {
            $this->info('Cancelado.');

            return 0;
        }

        $eliminador->eliminar($empresaContable);

        Comunidad::where('empresa_contable_id', $empresaContable->id)->update(['empresa_contable_id' => null]);

        $this->info("Contabilidad de '{$empresaContable->razon_social}' borrada.");

        return 0;
    }
}

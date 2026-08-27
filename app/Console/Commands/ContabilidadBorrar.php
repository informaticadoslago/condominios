<?php

namespace App\Console\Commands;

use App\Models\Comunidad;
use App\Models\EmpresaContable;
use App\Models\Sociedad;
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

        // La gestión guarda a qué empresa contable lleva sus libros cada comunidad y cada
        // sociedad, y esa columna no tiene clave ajena (la contabilidad no conoce a la
        // gestión). Si la empresa se va, ese enlace se queda apuntando a la nada: se avisa
        // y se quita.
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

            $this->warn('Sus datos de gestión (recibos, presupuestos, facturas) NO se tocan: se les quita el enlace y el código de cuenta contable de sus cuentas bancarias, que ya no existe.');
        }

        $sociedades = Sociedad::with('persona')
            ->where('empresa_contable_id', $empresaContable->id)
            ->get();

        if ($sociedades->isNotEmpty()) {
            $this->newLine();
            $this->warn("Además, {$sociedades->count()} sociedad(es) llevan sus libros en esta empresa y se quedarán SIN contabilidad enlazada:");

            foreach ($sociedades as $sociedad) {
                $nombre = $sociedad->persona->razon_social ?? $sociedad->persona->nombre_comercial ?? "#{$sociedad->id}";
                $this->warn("  - {$nombre} (#{$sociedad->id})");
            }

            $this->warn('Sus datos de gestión NO se tocan: se les quita el enlace y el código de cuenta contable de sus cuentas bancarias, que ya no existe.');
        }

        $this->newLine();

        if (! $this->confirm('¿Continuar?', false)) {
            $this->info('Cancelado.');

            return 0;
        }

        $eliminador->eliminar($empresaContable);

        Comunidad::where('empresa_contable_id', $empresaContable->id)->update(['empresa_contable_id' => null]);
        Sociedad::where('empresa_contable_id', $empresaContable->id)->update(['empresa_contable_id' => null]);

        // El código de cuenta contable de cada cuenta bancaria apuntaba a un plan que ya
        // no existe. Si se deja, el próximo enlace lo ve "ya relleno" y no crea la
        // subcuenta nueva en la empresa contable que toque después.
        foreach ($comunidades as $comunidad) {
            $comunidad->cuentasBancarias()->update(['cuenta_contable' => null]);
        }

        foreach ($sociedades as $sociedad) {
            $sociedad->cuentasBancarias()->update(['cuenta_contable' => null]);
        }

        $this->info("Contabilidad de '{$empresaContable->razon_social}' borrada.");

        return 0;
    }
}

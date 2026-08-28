<?php

namespace App\Livewire\Sociedades\Proveedores;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConBajaPorEstado;
use App\Livewire\Traits\ConFiltroEstado;
use App\Livewire\Traits\ConHistorialEstadoModal;
use App\Models\Estado;
use App\Models\PlantillaFactura;
use App\Models\Proveedor;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    use ConBajaPorEstado;
    use ConFiltroEstado;
    use ConHistorialEstadoModal;

    public function mount()
    {
        $this->sort      = 'id';
        $this->direction = 'desc';
    }

    #[On('proveedor-guardado')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    protected function modeloBaja(): string
    {
        return Proveedor::class;
    }

    protected function modeloEstado(): string
    {
        return Estado::class;
    }

    protected function modeloHistorial(): string
    {
        return Proveedor::class;
    }

    public function definicionesFiltro(): array
    {
        return [
            $this->filtroEstado(),
        ];
    }

    public function columnasDisponibles(): array
    {
        return [
            'nombre'    => __('Nombre'),
            'documento' => __('Documento'),
            'estado'    => __('Estado'),
        ];
    }

    /**
     * Se reimplementan en vez de dejar el ConBajaPorEstado del trait tal cual: un
     * proveedor no se puede borrar (por eso existe el estado de baja), y la baja/
     * reactivación solo puede tocar proveedores de la sociedad activa — el trait
     * genérico busca por id a secas, sin ese scope.
     */
    #[On('ejecutarBaja')]
    public function ejecutarBaja($id)
    {
        $proveedor = Proveedor::whereKey($id)
            ->deSociedad(session('sociedad_actual_id'))
            ->first();

        if ($proveedor) {
            $proveedor->update(['estado_id' => Proveedor::ESTADO_BAJA]);
            $this->dispatch('toast-success', ['title' => __('Proveedor dado de baja')]);
        }
    }

    #[On('ejecutarReactivar')]
    public function ejecutarReactivar($id)
    {
        $proveedor = Proveedor::whereKey($id)
            ->deSociedad(session('sociedad_actual_id'))
            ->first();

        if ($proveedor) {
            $proveedor->update(['estado_id' => Proveedor::ESTADO_ACTIVO]);
            $this->dispatch('toast-success', ['title' => __('Proveedor reactivado')]);
        }
    }

    /**
     * Vía de escape para un proveedor de baja que sobra del todo (duplicado, prueba...):
     * borra el proveedor y sus documentos (con el PDF del disco, uno a uno para que
     * dispare el evento que los borra). No tiene botón propio a propósito — se dispara
     * con mayús+clic sobre "Reactivar" (ver blade) para que no sea un botón más al lado
     * de "Dar de baja" tentando a pulsarlo sin querer.
     */
    public function confirmarBorrarDefinitivo($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Borrar este proveedor definitivamente?'),
            'text'               => __('Se borran también todos sus documentos (con el PDF del disco). Esta acción NO se puede deshacer.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, borrar todo'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarBorrarDefinitivo',
            'cancelCallback'     => 'borrarDefinitivoCancelado',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarBorrarDefinitivo')]
    public function ejecutarBorrarDefinitivo($id)
    {
        $proveedor = Proveedor::with(['persona', 'documentos'])
            ->deSociedad(session('sociedad_actual_id'))
            ->find($id);

        if (! $proveedor) {
            return;
        }

        $cif = $proveedor->persona->documento_identificativo;

        // Uno a uno (no un delete masivo): así dispara Documento::deleted, que borra
        // también el fichero del disco.
        foreach ($proveedor->documentos as $documento) {
            $documento->delete();
        }

        if ($cif) {
            // Global por CIF, no por proveedor (ver Documento::consolidarFichero): si no se
            // borra aquí, se queda huérfana y la próxima factura de este CIF la reutilizaría.
            PlantillaFactura::where('cif', $cif)->delete();
        }

        $proveedor->delete();

        $this->dispatch('toast-success', ['title' => __('Proveedor y documentos borrados')]);
    }

    #[On('borrarDefinitivoCancelado')]
    public function borrarDefinitivoCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = $this->aplicarFiltros(
            Proveedor::with(['persona', 'estado'])
                ->withCount('historialEstados')
                ->deSociedad(session('sociedad_actual_id'))
        )
            ->when($search, function ($q) use ($search) {
                $q->whereHasMorph('persona', [\App\Models\PersonaSociedad::class], fn ($p) => $p
                    ->buscarNombreCompleto($search)
                    ->orWhere('documento_identificativo', 'like', "%{$search}%"));
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.sociedades.proveedores.lista', compact('items'));
    }
}

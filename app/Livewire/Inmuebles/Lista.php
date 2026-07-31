<?php

namespace App\Livewire\Inmuebles;

use App\Livewire\ListaComponent;
use App\Models\Borrador;
use App\Models\Inmueble;
use App\Models\TipoInmueble;
use App\Models\Titularidad;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    public function mount()
    {
        $this->sort      = 'id';
        $this->direction = 'desc';
    }

    public function confirmarEliminar($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Eliminar inmueble?'),
            'text'               => __('Esta acción no se puede deshacer.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, eliminar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarEliminarInmueble',
            'cancelCallback'     => 'eliminarInmuebleCancelado',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarEliminarInmueble')]
    public function ejecutarEliminar($id)
    {
        $inmueble = Inmueble::where('comunidad_id', session('comunidad_actual_id'))->find($id);
        if ($inmueble) {
            // Las FK son restrict: hay que soltar las relaciones antes de borrar. Ojo:
            // propietarios() solo ve las titularidades VIGENTES, así que para no dejar
            // huérfanas las cerradas (histórico), se borran todas por inmueble_id directo.
            Titularidad::where('inmueble_id', $inmueble->id)->delete();
            $inmueble->gruposDeReparto()->detach();
            $inmueble->delete();
            $this->dispatch('toast-success', ['title' => __('Inmueble eliminado')]);
        }
    }

    #[On('eliminarInmuebleCancelado')]
    public function eliminarCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function confirmarDescartarBorrador($borradorId)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Descartar este inmueble sin terminar?'),
            'text'               => __('Esta acción no se puede deshacer.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, descartar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarDescartarBorrador',
            'cancelCallback'     => 'descartarBorradorCancelado',
            'id'                 => $borradorId,
        ]);
    }

    #[On('ejecutarDescartarBorrador')]
    public function ejecutarDescartarBorrador($id)
    {
        // Nada real llega a existir en un borrador de alta (ver DatosStep/PropietariosStep):
        // descartar es solo borrar la fila, no hay ningún Inmueble/Propietario que limpiar.
        Borrador::delUsuario()->deTipo(Borrador::TIPO_INMUEBLE)->whereKey($id)->delete();
    }

    #[On('descartarBorradorCancelado')]
    public function descartarBorradorCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $borradores = Borrador::delUsuario()->deTipo(Borrador::TIPO_INMUEBLE)
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn (Borrador $borrador) => ($borrador->payload['datos']['comunidad_id'] ?? null) == session('comunidad_actual_id'))
            ->map(function (Borrador $borrador) {
                $datos = $borrador->payload['datos'] ?? [];
                $borrador->tipoInmuebleDescripcion = TipoInmueble::find($datos['tipo_inmueble_id'] ?? null)?->descripcion;
                $borrador->planta = $datos['planta'] ?? null;
                $borrador->puerta = $datos['puerta'] ?? null;

                return $borrador;
            })
            ->values();

        $items = $this->aplicarFiltros(
            Inmueble::with(['ocupacion', 'tipoInmueble', 'propietarios.persona'])
                ->where('comunidad_id', session('comunidad_actual_id'))
                ->when($search, function ($q) use ($search) {
                    $q->where('puerta', 'like', "%{$search}%")
                        ->orWhere('referencia_catastral', 'like', "%{$search}%");
                })
        )
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        // Suma sobre TODOS los inmuebles de la comunidad (no solo la página o el
        // filtro actuales): lo que se comprueba es si la comunidad está
        // completamente repartida al 100%, no el resultado de la búsqueda.
        $sumaCoeficientes = Inmueble::where('comunidad_id', session('comunidad_actual_id'))->sum('coeficiente');

        return view('livewire.inmuebles.lista', compact('items', 'borradores', 'sumaCoeficientes'));
    }
}

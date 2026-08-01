<?php

namespace App\Livewire\Propietarios;

use App\Livewire\ListaComponent;
use App\Models\Borrador;
use App\Models\Propietario;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    public function mount()
    {
        $this->sort      = 'id';
        $this->direction = 'desc';
    }

    public function confirmarDescartarBorrador($borradorId)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Descartar este propietario sin terminar?'),
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
        // Si ya había creado la persona/propietario real antes de dejarlo a medias,
        // eso no se toca: descartar el borrador solo abandona lo que faltaba por rellenar.
        Borrador::delUsuario()->deTipo(Borrador::TIPO_PROPIETARIO)->whereKey($id)->delete();
    }

    #[On('descartarBorradorCancelado')]
    public function descartarBorradorCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function confirmarEliminar($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Eliminar propietario?'),
            'text'               => __('Esta acción no se puede deshacer.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, eliminar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarEliminarPropietario',
            'cancelCallback'     => 'eliminarPropietarioCancelado',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarEliminarPropietario')]
    public function ejecutarEliminar($id)
    {
        Propietario::whereKey($id)
            ->whereHas('persona', fn ($p) => $p->where('comunidad_id', session('comunidad_actual_id')))
            ->delete();
        $this->dispatch('toast-success', ['title' => __('Propietario eliminado')]);
    }

    #[On('eliminarPropietarioCancelado')]
    public function eliminarCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $borradores = Borrador::delUsuario()->deTipo(Borrador::TIPO_PROPIETARIO)
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn (Borrador $borrador) => ($borrador->payload['datos']['comunidad_id'] ?? null) == session('comunidad_actual_id'))
            ->map(function (Borrador $borrador) {
                $datos = $borrador->payload['datos'] ?? [];
                $borrador->nombreBorrador = trim(($datos['documento_identificativo'] ?? '').' — '.
                    ($datos['nombre'] ?? $datos['razon_social'] ?? '').' '.($datos['apellido1'] ?? ''));

                return $borrador;
            })
            ->values();

        $items = Propietario::with('persona')
            ->whereHas('persona', fn ($p) => $p->where('comunidad_id', session('comunidad_actual_id')))
            ->when($search, function ($q) use ($search) {
                $q->whereHas('persona', fn ($p) => $p
                    ->buscarNombreCompleto($search)
                    ->orWhere('documento_identificativo', 'like', "%{$search}%"));
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.propietarios.lista', compact('items', 'borradores'));
    }
}

<?php

namespace App\Livewire\Catalogos;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConBajaPorEstado;
use Illuminate\Database\QueryException;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    use ConBajaPorEstado;

    public string $clave;
    public string $modelo;
    public string $titulo;
    public ?string $subtitulo = null;
    public bool $bloqueado = false;

    protected function modeloBaja(): string
    {
        return $this->modelo;
    }

    // "estados" es de solo lectura: ninguna acción de baja/reactivar/borrado le aplica,
    // aunque alguien la dispare a mano (p.ej. desde la consola del navegador).
    public function confirmarBaja($id)
    {
        if ($this->bloqueado) {
            return;
        }
        parent::confirmarBaja($id);
    }

    public function confirmarReactivar($id)
    {
        if ($this->bloqueado) {
            return;
        }
        parent::confirmarReactivar($id);
    }

    public function mount(string $clave)
    {
        $config = config("catalogos.{$clave}") ?? abort(404);

        $this->clave      = $clave;
        $this->modelo     = $config['modelo'];
        $this->titulo     = $config['titulo'];
        $this->subtitulo  = $config['subtitulo'] ?? null;
        $this->bloqueado  = $config['bloqueado'] ?? false;

        $this->sort      = 'descripcion';
        $this->direction = 'asc';
    }

    #[On('catalogo-guardado')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    /** Mayús+clic en Eliminar: aviso más fuerte, deja claro que no tiene vuelta atrás. */
    public function confirmarEliminarFisico($id)
    {
        if ($this->bloqueado) {
            return;
        }

        $this->dispatch('swalConfirm', [
            'title'              => __('¿Borrar definitivamente?'),
            'text'               => __('Esto borra la fila de la base de datos (y su historial de cambios de estado, si lo tiene). No se puede deshacer.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, borrar definitivamente'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarEliminarFisico',
            'cancelCallback'     => 'eliminarFisicoCancelado',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarEliminarFisico')]
    public function ejecutarEliminarFisico($id)
    {
        if ($this->bloqueado) {
            return;
        }

        $modelo = $this->modelo;
        $item   = $modelo::find($id);
        if (! $item) {
            return;
        }

        try {
            // Si el modelo lleva historial de cambios de estado (ConHistorialEstado),
            // se borra también: si no, quedaría huérfano tras borrar la fila.
            if (method_exists($item, 'historialEstados')) {
                $item->historialEstados()->delete();
            }

            $item->delete();
            $this->dispatch('toast-success', ['title' => __('Registro borrado definitivamente')]);
        } catch (QueryException $e) {
            // SQLSTATE 23000: violación de integridad (la FK restrict de otra tabla la usa).
            if ($e->getCode() === '23000') {
                $this->dispatch('toast-error', ['title' => __('No se puede borrar: hay otros registros que lo usan')]);
            } else {
                throw $e;
            }
        }
    }

    #[On('eliminarFisicoCancelado')]
    public function eliminarFisicoCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function render()
    {
        $search = trim($this->search ?? '');
        $modelo = $this->modelo;

        $items = $modelo::with('estado')
            ->when($search, fn ($q) => $q->where('descripcion', 'like', "%{$search}%"))
            ->orderBy($this->sort, $this->direction)
            ->orderBy('id')
            ->paginate($this->lineasXPagina);

        return view('livewire.catalogos.lista', compact('items'));
    }
}

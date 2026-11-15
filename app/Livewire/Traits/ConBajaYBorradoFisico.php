<?php

namespace App\Livewire\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

/**
 * Baja y reactivación por estado_id, y borrado definitivo con Mayús+clic, para una lista
 * cuyos registros no son de toda la aplicación sino de un horario, un curso…
 *
 * El componente que lo use implementa:
 *   registroDeLaLista($id): ?Model   el registro, solo si pertenece a lo que enseña la lista
 *   enUso(Model $registro): bool     si otro registro lo referencia (no se borra)
 * y el modelo tiene las constantes ESTADO_ACTIVO / ESTADO_BAJA.
 */
trait ConBajaYBorradoFisico
{
    abstract protected function registroDeLaLista($id): ?Model;

    abstract protected function enUso(Model $registro): bool;

    private function preguntar(string $titulo, string $texto, string $icono, string $color, string $boton, string $callback, $id): void
    {
        $this->dispatch('swalConfirm', [
            'title'              => $titulo,
            'text'               => $texto,
            'icon'               => $icono,
            'showCancelButton'   => true,
            'confirmButtonColor' => $color,
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => $boton,
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => $callback,
            'cancelCallback'     => 'bajaCancelada',
            'id'                 => $id,
        ]);
    }

    public function confirmarBaja($id)
    {
        $this->preguntar(__('¿Dar de baja?'), __('Se marcará como inactivo.'), 'warning', '#d33', __('Sí, dar de baja'), 'ejecutarBaja', $id);
    }

    #[On('ejecutarBaja')]
    public function ejecutarBaja($id)
    {
        if ($registro = $this->registroDeLaLista($id)) {
            $registro->update(['estado_id' => $registro::ESTADO_BAJA]);
            $this->dispatch('toast-success', ['title' => __('Registro dado de baja')]);
        }
    }

    public function confirmarReactivar($id)
    {
        $this->preguntar(__('¿Reactivar?'), __('Se marcará como activo.'), 'question', '#3085d6', __('Sí, reactivar'), 'ejecutarReactivar', $id);
    }

    #[On('ejecutarReactivar')]
    public function ejecutarReactivar($id)
    {
        if ($registro = $this->registroDeLaLista($id)) {
            $registro->update(['estado_id' => $registro::ESTADO_ACTIVO]);
            $this->dispatch('toast-success', ['title' => __('Registro reactivado')]);
        }
    }

    #[On('bajaCancelada')]
    public function bajaCancelada($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    /** Mayús+clic: borrado físico. En uso no se pregunta siquiera: se avisa y se queda como está. */
    public function confirmarEliminarFisico($id)
    {
        $registro = $this->registroDeLaLista($id);
        if (! $registro) {
            return;
        }
        if ($this->enUso($registro)) {
            $this->dispatch('toast-error', ['title' => __('No se puede borrar: hay otros registros que lo usan')]);

            return;
        }

        $this->preguntar(
            __('¿Borrar definitivamente?'),
            __('Esto borra la fila de la base de datos y su historial de cambios de estado. No se puede deshacer.'),
            'warning', '#d33', __('Sí, borrar definitivamente'), 'ejecutarEliminarFisico', $id
        );
    }

    #[On('ejecutarEliminarFisico')]
    public function ejecutarEliminarFisico($id)
    {
        $registro = $this->registroDeLaLista($id);
        if (! $registro) {
            return;
        }
        // Otra vez, por si ha empezado a usarse entre la pregunta y la respuesta.
        if ($this->enUso($registro)) {
            $this->dispatch('toast-error', ['title' => __('No se puede borrar: hay otros registros que lo usan')]);

            return;
        }

        DB::transaction(function () use ($registro) {
            $registro->historialEstados()->delete();
            $registro->delete();
        });
        $this->dispatch('toast-success', ['title' => __('Registro borrado definitivamente')]);
    }
}

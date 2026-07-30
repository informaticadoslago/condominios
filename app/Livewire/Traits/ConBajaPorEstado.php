<?php

namespace App\Livewire\Traits;

use Livewire\Attributes\On;

/**
 * Baja/reactivación por estado (columna legacy 'estado') con confirmación SweetAlert.
 *
 * El componente que lo use debe implementar:
 *   protected function modeloBaja(): string   // FQCN del modelo (con constantes ESTADO_ACTIVO / ESTADO_BAJA)
 */
trait ConBajaPorEstado
{
    public function confirmarBaja($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Dar de baja?'),
            'text'               => __('Se marcará como inactivo.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, dar de baja'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarBaja',
            'cancelCallback'     => 'bajaCancelada',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarBaja')]
    public function ejecutarBaja($id)
    {
        $clase    = $this->modeloBaja();
        $registro = $clase::find($id);
        if ($registro) {
            $registro->update(['estado_id' => $clase::ESTADO_BAJA]);
            $this->dispatch('toast-success', ['title' => __('Registro dado de baja')]);
        }
    }

    public function confirmarReactivar($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Reactivar?'),
            'text'               => __('Se marcará como activo.'),
            'icon'               => 'question',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#3085d6',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, reactivar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarReactivar',
            'cancelCallback'     => 'bajaCancelada',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarReactivar')]
    public function ejecutarReactivar($id)
    {
        $clase    = $this->modeloBaja();
        $registro = $clase::find($id);
        if ($registro) {
            $registro->update(['estado_id' => $clase::ESTADO_ACTIVO]);
            $this->dispatch('toast-success', ['title' => __('Registro reactivado')]);
        }
    }

    #[On('bajaCancelada')]
    public function bajaCancelada($id = null)
    {
        // el usuario canceló; no hacemos nada
    }
}

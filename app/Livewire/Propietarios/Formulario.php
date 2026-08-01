<?php

namespace App\Livewire\Propietarios;

use App\Models\Borrador;
use App\Models\Propietario;
use Livewire\Component;

/**
 * Página que aloja el wizard de alta/edición de propietario (App\Livewire\Propietarios\Crear\CrearPropietario).
 *
 * Alta y edición funcionan igual: nada se toca en las tablas reales hasta
 * "Terminar" — todo vive mientras tanto en el payload JSON de un Borrador. Al
 * editar, se copia primero el estado real a un borrador nuevo (si no había ya
 * uno de esta misma edición) y a partir de ahí se trabaja solo sobre esa copia.
 * Calcada de Inmuebles\Formulario.
 */
class Formulario extends Component
{
    public ?int $propietarioId = null;

    // Solo tiene sentido al crear: es la comunidad activa en sesión, para que el
    // propietario nuevo quede en ella sin tener que elegirla.
    public ?int $comunidadId = null;

    public function mount(?Propietario $propietario = null)
    {
        if (! $propietario && ($borradorId = request()->integer('borrador'))) {
            $borrador = Borrador::delUsuario()->deTipo(Borrador::TIPO_PROPIETARIO)->find($borradorId);
            session(['propietario_borrador_id' => $borrador?->id]);

            if ($borrador && ! empty($borrador->payload['propietario_id'])) {
                $propietario = Propietario::find($borrador->payload['propietario_id']);
            }
        } elseif ($propietario) {
            $borrador = $this->borradorDeEdicion($propietario->id);
            session(['propietario_borrador_id' => $borrador->id]);
        } else {
            session()->forget('propietario_borrador_id');
        }

        // El middleware comunidad.activa ya comprueba que el usuario tiene acceso a la
        // comunidad activa; esto comprueba que el propietario concreto es de ESA comunidad.
        abort_if($propietario && $propietario->persona->comunidad_id != session('comunidad_actual_id'), 403);

        $this->propietarioId = $propietario?->id;
        $this->comunidadId   = $propietario ? null : session('comunidad_actual_id');
    }

    /** El borrador de sesión si ya es de este mismo propietario; si no, uno nuevo con su copia. */
    private function borradorDeEdicion(int $propietarioId): Borrador
    {
        $borradorId = session('propietario_borrador_id');
        $borrador   = $borradorId ? Borrador::delUsuario()->deTipo(Borrador::TIPO_PROPIETARIO)->find($borradorId) : null;

        if ($borrador && ($borrador->payload['propietario_id'] ?? null) == $propietarioId) {
            return $borrador;
        }

        // A diferencia de Inmuebles, aquí no hace falta copiar de golpe el estado real al
        // payload: cada paso (DatosStep, DireccionStep...) ya sabe cargar directamente de
        // las tablas reales si el payload todavía no tiene su sección (ver sus mount()).
        return Borrador::create([
            'user_id' => auth()->id(),
            'tipo'    => Borrador::TIPO_PROPIETARIO,
            'payload' => ['propietario_id' => $propietarioId],
        ]);
    }

    public function render()
    {
        return view('livewire.propietarios.formulario');
    }
}

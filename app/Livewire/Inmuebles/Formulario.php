<?php

namespace App\Livewire\Inmuebles;

use App\Models\Borrador;
use App\Models\Inmueble;
use App\Models\Titularidad;
use Livewire\Component;

/**
 * Página que aloja el wizard de alta/edición de inmueble (App\Livewire\Inmuebles\Crear\CrearInmueble).
 *
 * Alta y edición funcionan igual: nada se toca en las tablas reales hasta
 * "Terminar" — todo vive mientras tanto en el payload JSON de un Borrador. Al
 * editar, se copia primero el estado real a un borrador nuevo (si no había ya
 * uno de esta misma edición) y a partir de ahí se trabaja solo sobre esa copia.
 */
class Formulario extends Component
{
    public ?int $inmuebleId = null;

    // Solo tiene sentido al crear: es la comunidad activa en sesión, para que el
    // inmueble nuevo quede en ella sin tener que elegirla.
    public ?int $comunidadId = null;

    public function mount(?Inmueble $inmueble = null)
    {
        if (! $inmueble && ($borradorId = request()->integer('borrador'))) {
            // Retomar: puede ser un borrador de alta (sin inmueble real todavía) o
            // de una edición (con el id del inmueble real guardado en el payload).
            $borrador = Borrador::delUsuario()->deTipo(Borrador::TIPO_INMUEBLE)->find($borradorId);
            session(['inmueble_borrador_id' => $borrador?->id]);

            if ($borrador && ! empty($borrador->payload['inmueble_id'])) {
                $inmueble = Inmueble::find($borrador->payload['inmueble_id']);
            }
        } elseif ($inmueble) {
            // Entrar a editar un inmueble real: si no hay ya un borrador de ESTA
            // edición en la sesión actual, se crea uno copiando el estado real.
            $borrador = $this->borradorDeEdicion($inmueble->id);
            session(['inmueble_borrador_id' => $borrador->id]);
        } else {
            // Alta nueva desde cero: no arrastrar el borrador de una sesión anterior.
            session()->forget('inmueble_borrador_id');
        }

        // El middleware comunidad.activa ya comprueba que el usuario tiene acceso a la
        // comunidad activa; esto comprueba que el inmueble concreto es de ESA comunidad
        // (si no, alguien con acceso a la comunidad A podría editar/retomar un inmueble
        // de la comunidad B solo cambiando el id en la URL).
        abort_if($inmueble && $inmueble->comunidad_id != session('comunidad_actual_id'), 403);

        $this->inmuebleId  = $inmueble?->id;
        $this->comunidadId = $inmueble ? null : session('comunidad_actual_id');
    }

    /** El borrador de sesión si ya es de este mismo inmueble; si no, uno nuevo con su copia. */
    private function borradorDeEdicion(int $inmuebleId): Borrador
    {
        $borradorId = session('inmueble_borrador_id');
        $borrador   = $borradorId ? Borrador::delUsuario()->deTipo(Borrador::TIPO_INMUEBLE)->find($borradorId) : null;

        if ($borrador && ($borrador->payload['inmueble_id'] ?? null) == $inmuebleId) {
            return $borrador;
        }

        $inmueble = Inmueble::findOrFail($inmuebleId);

        return Borrador::create([
            'user_id' => auth()->id(),
            'tipo'    => Borrador::TIPO_INMUEBLE,
            'payload' => [
                'inmueble_id'  => $inmueble->id,
                'datos'        => $inmueble->only([
                    'comunidad_id', 'ocupacion_id', 'tipo_inmueble_id', 'planta', 'puerta', 'coeficiente', 'referencia_catastral',
                ]),
                'propietarios' => Titularidad::vigente()
                    ->where('inmueble_id', $inmueble->id)
                    ->with('propietario.persona')
                    ->get()
                    ->values()
                    ->map(fn (Titularidad $t, $i) => [
                        'ref'                   => $i,
                        'titularidad_id'        => $t->id,
                        'persona_comunidad_id'  => $t->propietario->persona_comunidad_id,
                        'persona_nueva'         => null,
                        'nombre'                => ($t->propietario->persona->documento_identificativo ?? '').' — '.$t->propietario->persona->nombreCompleto,
                        'cuota_percent'         => (float) $t->cuota_percent,
                        'causa'                 => $t->causa,
                    ])->all(),
            ],
        ]);
    }

    public function render()
    {
        return view('livewire.inmuebles.formulario');
    }
}

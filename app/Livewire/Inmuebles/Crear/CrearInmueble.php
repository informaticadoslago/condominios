<?php

namespace App\Livewire\Inmuebles\Crear;

use App\Livewire\Inmuebles\Crear\Steps\DatosStep;
use App\Livewire\Inmuebles\Crear\Steps\PropietariosStep;
use App\Models\Borrador;
use App\Models\TipoInmueble;
use App\Models\TipoOcupacion;
use Spatie\LivewireWizard\Components\WizardComponent;

/**
 * Wizard de alta/edición de inmueble: datos propios y propietarios.
 *
 * Alta y edición funcionan igual: nada es real hasta "Terminar". Mientras
 * tanto todo vive en el payload JSON de un Borrador (ver Inmuebles\Formulario,
 * DatosStep y PropietariosStep), cuyo id viaja en sesión
 * (`inmueble_borrador_id`) porque el propio wizard no comparte de forma
 * fiable el estado entre pasos.
 */
class CrearInmueble extends WizardComponent
{
    public const PASO_DATOS        = 'inmuebles.crear.steps.datos-step';
    public const PASO_PROPIETARIOS = 'inmuebles.crear.steps.propietarios-step';

    public ?int $inmuebleId = null;

    // Solo se usa al crear (ver App\Livewire\Inmuebles\Formulario): es la comunidad
    // activa en sesión, para fijarla en el primer paso sin tener que elegirla.
    public ?int $comunidadId = null;

    public function mount(?int $inmuebleId = null, ?int $comunidadId = null)
    {
        $this->inmuebleId  = $inmuebleId;
        $this->comunidadId = $comunidadId;
    }

    public function steps(): array
    {
        return [
            DatosStep::class,
            PropietariosStep::class,
        ];
    }

    public function initialState(): ?array
    {
        $semilla = $this->stepNames()
            ->mapWithKeys(fn (string $paso) => [$paso => ['inmuebleId' => $this->inmuebleId]])
            ->toArray();

        // Formulario::mount() ya garantiza que existe un borrador (nuevo o
        // recuperado) tanto en alta como en edición antes de llegar aquí.
        $borradorId = session('inmueble_borrador_id');
        $borrador   = $borradorId ? Borrador::delUsuario()->deTipo(Borrador::TIPO_INMUEBLE)->find($borradorId) : null;

        if ($borrador && ! empty($borrador->payload['datos'])) {
            $semilla[self::PASO_DATOS] = array_merge($semilla[self::PASO_DATOS], $borrador->payload['datos']);
        } else {
            // Lo más habitual es un piso ocupado por su propietario, así que se
            // preseleccionan para no obligar a elegirlos cada vez.
            $semilla[self::PASO_DATOS]['tipo_inmueble_id'] = TipoInmueble::PISO;
            $semilla[self::PASO_DATOS]['ocupacion_id']     = TipoOcupacion::PROPIETARIO;

            if ($this->comunidadId) {
                $semilla[self::PASO_DATOS]['comunidad_id'] = $this->comunidadId;
            }
        }

        return $semilla;
    }
}

<?php

namespace App\Livewire\Propietarios\Crear;

use App\Livewire\Propietarios\Crear\Steps\ContactosStep;
use App\Livewire\Propietarios\Crear\Steps\CuentaBancariaStep;
use App\Livewire\Propietarios\Crear\Steps\DatosStep;
use App\Livewire\Propietarios\Crear\Steps\DireccionStep;
use App\Models\Borrador;
use Spatie\LivewireWizard\Components\WizardComponent;

/**
 * Wizard de alta/edición de propietario: datos fiscales, dirección, contactos y cuenta
 * bancaria. Calcado de Inmuebles\Crear\CrearInmueble: nada es real hasta "Terminar",
 * todo vive mientras tanto en el payload JSON de un Borrador.
 */
class CrearPropietario extends WizardComponent
{
    public const PASO_DATOS      = 'propietarios.crear.steps.datos-step';
    public const PASO_DIRECCION  = 'propietarios.crear.steps.direccion-step';
    public const PASO_CONTACTOS  = 'propietarios.crear.steps.contactos-step';
    public const PASO_CUENTA     = 'propietarios.crear.steps.cuenta-bancaria-step';

    public ?int $propietarioId = null;

    // Solo se usa al crear (ver Propietarios\Formulario): la comunidad activa en
    // sesión, para fijarla sin tener que elegirla.
    public ?int $comunidadId = null;

    // Abierto en un modal (p.ej. desde el alta de un inmueble) en vez de a página
    // completa: cambia cómo terminan "Salir"/"Terminar" (ver CrearPropietarioStep).
    public bool $embebido = false;

    public function mount(?int $propietarioId = null, ?int $comunidadId = null, bool $embebido = false)
    {
        $this->propietarioId = $propietarioId;
        $this->comunidadId   = $comunidadId;
        $this->embebido      = $embebido;
    }

    public function steps(): array
    {
        return [
            DatosStep::class,
            DireccionStep::class,
            ContactosStep::class,
            CuentaBancariaStep::class,
        ];
    }

    public function initialState(): ?array
    {
        $semilla = $this->stepNames()
            ->mapWithKeys(fn (string $paso) => [$paso => ['propietarioId' => $this->propietarioId, 'embebido' => $this->embebido]])
            ->toArray();

        // Formulario::mount() ya garantiza que existe un borrador (nuevo o
        // recuperado) tanto en alta como en edición antes de llegar aquí. Clave de
        // sesión distinta si está embebido, ver CrearPropietarioStep::claveBorrador().
        $claveBorrador = $this->embebido ? 'propietario_borrador_id_modal' : 'propietario_borrador_id';
        $borradorId    = session($claveBorrador);
        $borrador      = $borradorId ? Borrador::delUsuario()->deTipo(Borrador::TIPO_PROPIETARIO)->find($borradorId) : null;

        if ($borrador && ! empty($borrador->payload['datos'])) {
            $semilla[self::PASO_DATOS] = array_merge($semilla[self::PASO_DATOS], $borrador->payload['datos']);
        } elseif ($this->comunidadId) {
            $semilla[self::PASO_DATOS]['comunidad_id'] = $this->comunidadId;
        }

        if ($borrador && ! empty($borrador->payload['direccion'])) {
            $semilla[self::PASO_DIRECCION] = array_merge($semilla[self::PASO_DIRECCION], $borrador->payload['direccion']);
        }

        if ($borrador && ! empty($borrador->payload['contactos'])) {
            $semilla[self::PASO_CONTACTOS] = array_merge($semilla[self::PASO_CONTACTOS], $borrador->payload['contactos']);
        }

        if ($borrador && ! empty($borrador->payload['cuenta_bancaria'])) {
            $semilla[self::PASO_CUENTA] = array_merge($semilla[self::PASO_CUENTA], $borrador->payload['cuenta_bancaria']);
        }

        return $semilla;
    }
}

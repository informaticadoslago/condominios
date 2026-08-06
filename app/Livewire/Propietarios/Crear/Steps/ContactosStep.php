<?php

namespace App\Livewire\Propietarios\Crear\Steps;

use App\Livewire\Propietarios\Crear\CrearPropietarioStep;
use App\Models\Borrador;
use App\Models\Comunidad;
use App\Models\Contacto;
use App\Models\Estado;
use App\Models\Propietario;
use App\Models\TipoContacto;
use App\Rules\Telefono;
use App\Services\Propietarios\EnviarVerificacionCorreo;
use Livewire\Attributes\Locked;

class ContactosStep extends CrearPropietarioStep
{
    #[Locked]
    public ?int $propietarioId = null;

    public ?string $telefono = null;
    public ?string $email    = null;

    public bool $cargado = false;

    public function stepInfo(): array
    {
        return ['label' => __('Contactos')];
    }

    public function mount()
    {
        if ($this->cargado) {
            return;
        }
        $this->cargado = true;

        if ($this->propietarioId && ! $this->telefono && ! $this->email) {
            $contactos = Propietario::find($this->propietarioId)?->persona?->contactos;

            $this->telefono = $contactos?->whereIn('tipo_contacto_id', [TipoContacto::MOVIL, TipoContacto::TELEFONO])->first()?->valor;
            $this->email    = $contactos?->where('tipo_contacto_id', TipoContacto::EMAIL)->first()?->valor;
        }
    }

    protected function rules()
    {
        return [
            'telefono' => ['nullable', 'string', 'max:30', new Telefono()],
            'email'    => ['nullable', 'email', 'max:150'],
        ];
    }

    protected function validationAttributes()
    {
        return [
            'telefono' => __('teléfono'),
            'email'    => __('email'),
        ];
    }

    protected function validarYGuardar(): void
    {
        $data = $this->validate();

        $borrador = $this->borradorActual();
        $payload  = $borrador?->payload ?? [];
        $payload['contactos'] = $data;
        $borrador?->update(['payload' => $payload]);
    }

    private function borradorActual(): ?Borrador
    {
        $borradorId = session($this->claveBorrador());

        return $borradorId ? Borrador::delUsuario()->deTipo(Borrador::TIPO_PROPIETARIO)->find($borradorId) : null;
    }

    /**
     * Contacto de correo YA GUARDADO de este propietario, para poder decir si está
     * validado. No es lo que hay escrito en el campo: si se acaba de teclear otra
     * dirección distinta, esa todavía no existe y no puede estar validada.
     */
    private function contactoCorreo(): ?Contacto
    {
        if (! $this->propietarioId) {
            return null;
        }

        return Propietario::find($this->propietarioId)?->persona?->contactos
            ->where('tipo_contacto_id', TipoContacto::EMAIL)
            ->where('estado_id', Estado::ESTADO_ACTIVO)
            ->first();
    }

    public function enviarVerificacion(EnviarVerificacionCorreo $servicio): void
    {
        $propietario = $this->propietarioId ? Propietario::find($this->propietarioId) : null;
        $comunidad   = Comunidad::find(session('comunidad_actual_id'));

        if (! $propietario || ! $comunidad) {
            return;
        }

        $enviados = $servicio->aPropietario($propietario, $comunidad);

        $this->dispatch($enviados ? 'toast-success' : 'toast-error', [
            'title' => $enviados
                ? __('Correo de verificación enviado')
                : __('No tiene ninguna dirección de correo pendiente de confirmar'),
        ]);
    }

    public function render()
    {
        $contacto = $this->contactoCorreo();

        return view('livewire.propietarios.crear.steps.contactos-step', [
            'contactoCorreo' => $contacto,
            // El botón solo tiene sentido sobre una dirección ya guardada y sin validar,
            // y solo si es la que se está viendo en el campo.
            'puedeVerificar' => $contacto && ! $contacto->estaValidado() && $contacto->valor === $this->email,
        ]);
    }
}

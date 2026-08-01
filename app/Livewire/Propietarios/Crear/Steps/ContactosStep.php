<?php

namespace App\Livewire\Propietarios\Crear\Steps;

use App\Livewire\Propietarios\Crear\CrearPropietarioStep;
use App\Models\Borrador;
use App\Models\Propietario;
use App\Models\TipoContacto;
use App\Rules\Telefono;
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

    public function render()
    {
        return view('livewire.propietarios.crear.steps.contactos-step');
    }
}

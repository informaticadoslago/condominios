<?php

namespace App\Livewire\Propietarios\Crear\Steps;

use App\Livewire\Propietarios\Crear\CrearPropietarioStep;
use App\Models\Borrador;
use App\Models\Municipio;
use App\Models\Propietario;
use App\Models\Provincia;
use Livewire\Attributes\Locked;

class DireccionStep extends CrearPropietarioStep
{
    #[Locked]
    public ?int $propietarioId = null;

    public ?string $direccion1 = null;
    public ?string $numero     = null;
    public ?string $piso       = null;
    public ?string $puerta     = null;
    public ?string $codigo_postal = null;
    public ?int $provincia_id  = null;
    public ?int $municipio_id  = null;

    public bool $cargado = false;

    public function stepInfo(): array
    {
        return ['label' => __('Dirección')];
    }

    public function mount()
    {
        if ($this->cargado) {
            return;
        }
        $this->cargado = true;

        if ($this->propietarioId && ! $this->direccion1) {
            $domicilio = Propietario::find($this->propietarioId)?->persona?->domicilio;

            if ($domicilio) {
                $this->direccion1     = $domicilio->direccion1;
                $this->numero         = $domicilio->numero;
                $this->piso           = $domicilio->piso;
                $this->puerta         = $domicilio->puerta;
                $this->codigo_postal  = $domicilio->codigo_postal;
                $this->provincia_id   = $domicilio->provincia_id;
                $this->municipio_id   = $domicilio->municipio_id;
            }
        }
    }

    public function updatedProvinciaId(): void
    {
        // Cambiar de provincia invalida el municipio ya elegido de la anterior.
        $this->municipio_id = null;
    }

    public function municipios()
    {
        return $this->provincia_id ? Municipio::deProvincia($this->provincia_id)->ordenaPorNombre()->get() : collect();
    }

    protected function rules()
    {
        return [
            'direccion1'    => ['nullable', 'string', 'max:150'],
            'numero'        => ['nullable', 'string', 'max:10'],
            'piso'          => ['nullable', 'string', 'max:10'],
            'puerta'        => ['nullable', 'string', 'max:10'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'provincia_id'  => ['nullable', 'exists:provincias,id'],
            'municipio_id'  => ['nullable', 'exists:municipios,id', 'required_with:provincia_id'],
        ];
    }

    protected function validationAttributes()
    {
        return [
            'direccion1'    => __('dirección'),
            'codigo_postal' => __('código postal'),
            'provincia_id'  => __('provincia'),
            'municipio_id'  => __('municipio'),
        ];
    }

    protected function validarYGuardar(): void
    {
        $data = $this->validate();

        $borrador = $this->borradorActual();
        $payload  = $borrador?->payload ?? [];
        $payload['direccion'] = $data;
        $borrador?->update(['payload' => $payload]);
    }

    private function borradorActual(): ?Borrador
    {
        $borradorId = session($this->claveBorrador());

        return $borradorId ? Borrador::delUsuario()->deTipo(Borrador::TIPO_PROPIETARIO)->find($borradorId) : null;
    }

    public function render()
    {
        return view('livewire.propietarios.crear.steps.direccion-step', [
            'provincias' => Provincia::ordenaPorNombre()->get(),
            'municipios' => $this->municipios(),
        ]);
    }
}

<?php

namespace App\Livewire\Maestros\Periodicidades;

use App\Models\TipoPeriodicidadPago;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public bool $abrir = false;
    public ?int $itemId = null;

    public string $descripcion = '';
    public ?int $meses = null;

    protected function rules()
    {
        return [
            'descripcion' => ['required', 'string', 'max:50', Rule::unique('tipo_periodicidad_pagos', 'descripcion')->ignore($this->itemId)],
            'meses'       => ['required', 'integer', 'min:1'],
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima longitud de :attribute = :max',
            'min'      => ':attribute debe ser mayor o igual a :min',
            'unique'   => 'Ya existe una periodicidad con esa descripción',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'descripcion' => __('descripción'),
            'meses'       => __('meses'),
        ];
    }

    #[On('abrir-crear-periodicidad')]
    public function crear()
    {
        $this->reset(['itemId', 'descripcion', 'meses']);
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('periodicidad-editar')]
    public function editar($id)
    {
        $item = TipoPeriodicidadPago::find($id);
        if (! $item) {
            return;
        }
        $this->itemId      = $item->id;
        $this->descripcion = $item->descripcion;
        $this->meses       = $item->meses;
        $this->resetValidation();
        $this->abrir = true;
    }

    public function guardar()
    {
        $data = $this->validate();

        if ($this->itemId) {
            TipoPeriodicidadPago::whereKey($this->itemId)->update($data);
            $this->dispatch('toast-success', ['title' => __('Periodicidad modificada')]);
        } else {
            TipoPeriodicidadPago::create($data + ['estado_id' => TipoPeriodicidadPago::ESTADO_ACTIVO]);
            $this->dispatch('toast-success', ['title' => __('Periodicidad creada')]);
        }

        $this->dispatch('periodicidad-guardada');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.maestros.periodicidades.formulario');
    }
}

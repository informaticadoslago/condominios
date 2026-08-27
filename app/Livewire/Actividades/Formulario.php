<?php

namespace App\Livewire\Actividades;

use App\Models\Actividad;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public bool $abrir = false;
    public ?int $itemId = null;

    public string $nombre = '';

    protected function rules()
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima longitud de :attribute = :max',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'nombre' => __('nombre'),
        ];
    }

    #[On('abrir-crear-actividad')]
    public function crear()
    {
        $this->reset(['itemId', 'nombre']);
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('actividad-editar')]
    public function editar($id)
    {
        $item = Actividad::where('comunidad_id', session('comunidad_actual_id'))->find($id);
        if (! $item) {
            return;
        }
        $this->itemId = $item->id;
        $this->nombre = $item->nombre;
        $this->resetValidation();
        $this->abrir = true;
    }

    public function guardar()
    {
        $data = $this->validate();

        if ($this->itemId) {
            $actividad = Actividad::where('comunidad_id', session('comunidad_actual_id'))->findOrFail($this->itemId);
            $actividad->update($data);
            $this->dispatch('toast-success', ['title' => __('Actividad modificada')]);
        } else {
            // Nace sin proyecto_contable_id: si la comunidad ya lleva contabilidad, se
            // enlaza sola (Actividad::booted()).
            Actividad::create($data + ['comunidad_id' => session('comunidad_actual_id')]);
            $this->dispatch('toast-success', ['title' => __('Actividad creada')]);
        }

        $this->dispatch('actividad-guardada');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.actividades.formulario');
    }
}

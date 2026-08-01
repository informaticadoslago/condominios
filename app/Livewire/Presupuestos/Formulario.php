<?php

namespace App\Livewire\Presupuestos;

use App\Models\Presupuesto;
use App\Models\TipoEstadoPresupuesto;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public bool $abrir = false;
    public ?int $itemId = null;

    public string $nombre = '';
    public ?int $anho = null;

    protected function rules()
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'anho'   => ['required', 'integer', 'digits:4'],
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima longitud de :attribute = :max',
            'digits'   => 'El :attribute debe tener exactamente :digits dígitos',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'nombre' => __('nombre'),
            'anho'   => __('año'),
        ];
    }

    #[On('abrir-crear-presupuesto')]
    public function crear()
    {
        $this->reset(['itemId', 'nombre', 'anho']);
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('presupuesto-editar')]
    public function editar($id)
    {
        $item = Presupuesto::where('comunidad_id', session('comunidad_actual_id'))->find($id);
        if (! $item) {
            return;
        }
        $this->itemId = $item->id;
        $this->nombre = $item->nombre;
        $this->anho   = $item->anho;
        $this->resetValidation();
        $this->abrir = true;
    }

    public function guardar()
    {
        $data = $this->validate();

        if ($this->itemId) {
            $presupuesto = Presupuesto::where('comunidad_id', session('comunidad_actual_id'))->findOrFail($this->itemId);
            $presupuesto->update($data);
            $this->dispatch('toast-success', ['title' => __('Presupuesto modificado')]);
        } else {
            Presupuesto::create($data + [
                'comunidad_id' => session('comunidad_actual_id'),
                'estado_id'    => TipoEstadoPresupuesto::PROVISIONAL,
            ]);
            $this->dispatch('toast-success', ['title' => __('Presupuesto creado')]);
        }

        $this->dispatch('presupuesto-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.presupuestos.formulario');
    }
}

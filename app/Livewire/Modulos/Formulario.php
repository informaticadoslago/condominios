<?php

namespace App\Livewire\Modulos;

use App\Models\Modulo;
use Illuminate\Validation\Rule;
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
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('modulos', 'nombre')
                    ->where('horario_id', session('horario_actual_id'))
                    ->ignore($this->itemId),
            ],
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima longitud de :attribute = :max',
            'unique'   => 'Ya existe un módulo con ese nombre',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'nombre' => __('nombre'),
        ];
    }

    #[On('abrir-crear-modulo')]
    public function crear()
    {
        $this->reset(['itemId', 'nombre']);
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('modulo-editar')]
    public function editar($id)
    {
        $item = Modulo::where('horario_id', session('horario_actual_id'))->find($id);
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
            Modulo::whereKey($this->itemId)->update($data);
            $this->dispatch('toast-success', ['title' => __('Módulo modificado')]);
        } else {
            $data['horario_id'] = session('horario_actual_id');
            Modulo::create($data);
            $this->dispatch('toast-success', ['title' => __('Módulo creado')]);
        }

        $this->dispatch('modulo-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.modulos.formulario');
    }
}

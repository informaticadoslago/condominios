<?php

namespace App\Livewire\Asignaturas;

use App\Models\Asignatura;
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
                Rule::unique('asignaturas', 'nombre')
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
            'unique'   => 'Ya existe una asignatura con ese nombre',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'nombre' => __('nombre'),
        ];
    }

    #[On('abrir-crear-asignatura')]
    public function crear()
    {
        $this->reset(['itemId', 'nombre']);
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('asignatura-editar')]
    public function editar($id)
    {
        $item = Asignatura::where('horario_id', session('horario_actual_id'))->find($id);
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
            Asignatura::whereKey($this->itemId)->update($data);
            $this->dispatch('toast-success', ['title' => __('Asignatura modificada')]);
        } else {
            $data['horario_id'] = session('horario_actual_id');
            Asignatura::create($data);
            $this->dispatch('toast-success', ['title' => __('Asignatura creada')]);
        }

        $this->dispatch('asignatura-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.asignaturas.formulario');
    }
}

<?php

namespace App\Livewire\Horarios;

use App\Models\Horario;
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
            'nombre' => ['required', 'string', 'max:100', Rule::unique('horarios', 'nombre')->ignore($this->itemId)],
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima longitud de :attribute = :max',
            'unique'   => 'Ya existe un horario con ese nombre',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'nombre' => __('nombre'),
        ];
    }

    #[On('abrir-crear-horario')]
    public function crear()
    {
        $this->reset(['itemId', 'nombre']);
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('horario-editar')]
    public function editar($id)
    {
        $item = Horario::find($id);
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
            Horario::whereKey($this->itemId)->update($data);
            $this->dispatch('toast-success', ['title' => __('Horario modificado')]);
        } else {
            Horario::create($data);
            $this->dispatch('toast-success', ['title' => __('Horario creado')]);
        }

        $this->dispatch('horario-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.horarios.formulario');
    }
}

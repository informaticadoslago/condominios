<?php

namespace App\Livewire\Catalogos;

use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public string $clave;
    public string $modelo;
    public bool $bloqueado = false;

    public bool $abrir = false;
    public ?int $itemId = null;

    public string $descripcion = '';

    public function mount(string $clave)
    {
        $config = config("catalogos.{$clave}") ?? abort(404);

        $this->clave     = $clave;
        $this->modelo    = $config['modelo'];
        $this->bloqueado = $config['bloqueado'] ?? false;
    }

    protected function tabla(): string
    {
        return (new $this->modelo)->getTable();
    }

    protected function rules()
    {
        return [
            'descripcion' => ['required', 'string', 'max:50', Rule::unique($this->tabla(), 'descripcion')->ignore($this->itemId)],
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima longitud de :attribute = :max',
            'unique'   => 'Ya existe un registro con esa descripción',
        ];
    }

    protected function validationAttributes()
    {
        return ['descripcion' => __('descripción')];
    }

    #[On('abrir-crear-catalogo')]
    public function crear()
    {
        if ($this->bloqueado) {
            return;
        }

        $this->reset(['itemId', 'descripcion']);
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('catalogo-editar')]
    public function editar($id)
    {
        if ($this->bloqueado) {
            return;
        }

        $modelo = $this->modelo;
        $item   = $modelo::find($id);
        if (! $item) {
            return;
        }
        $this->itemId      = $item->id;
        $this->descripcion = $item->descripcion;
        $this->resetValidation();
        $this->abrir = true;
    }

    public function guardar()
    {
        if ($this->bloqueado) {
            return;
        }

        $data   = $this->validate();
        $modelo = $this->modelo;

        if ($this->itemId) {
            $modelo::whereKey($this->itemId)->update($data);
            $this->dispatch('toast-success', ['title' => __('Registro modificado')]);
        } else {
            $modelo::create($data + ['estado_id' => $modelo::ESTADO_ACTIVO]);
            $this->dispatch('toast-success', ['title' => __('Registro creado')]);
        }

        $this->dispatch('catalogo-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.catalogos.formulario');
    }
}

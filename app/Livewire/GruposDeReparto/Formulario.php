<?php

namespace App\Livewire\GruposDeReparto;

use App\Models\GrupoDeReparto;
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

    #[On('abrir-crear-grupo-de-reparto')]
    public function crear()
    {
        $this->reset(['itemId', 'nombre']);
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('grupo-de-reparto-editar')]
    public function editar($id)
    {
        $item = GrupoDeReparto::where('comunidad_id', session('comunidad_actual_id'))->find($id);
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
            $grupo = GrupoDeReparto::where('comunidad_id', session('comunidad_actual_id'))->findOrFail($this->itemId);
            $grupo->update($data);
            $this->dispatch('toast-success', ['title' => __('Grupo de reparto modificado')]);
        } else {
            GrupoDeReparto::create($data + ['comunidad_id' => session('comunidad_actual_id')]);
            $this->dispatch('toast-success', ['title' => __('Grupo de reparto creado')]);
        }

        $this->dispatch('grupo-de-reparto-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.grupos-de-reparto.formulario');
    }
}

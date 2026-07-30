<?php
namespace App\Livewire\Maestros\FormasDePago;

use App\Models\FormaDePago;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public bool $abrir = false;
    public ?int $itemId = null;

    public string $descripcion = '';

    protected function rules()
    {
        return [
            'descripcion' => ['required', 'string', 'max:180', Rule::unique('formas_de_pago', 'descripcion')->ignore($this->itemId)],
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima longitud de :attribute = :max',
            'unique'   => 'Ya existe una forma de pago con esa descripción',
        ];
    }

    protected function validationAttributes()
    {
        return ['descripcion' => __('descripción')];
    }

    #[On('abrir-crear-forma-de-pago')]
    public function crear()
    {
        $this->reset(['itemId', 'descripcion']);
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('forma-de-pago-editar')]
    public function editar($id)
    {
        $item = FormaDePago::find($id);
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
        $data = $this->validate();

        if ($this->itemId) {
            FormaDePago::whereKey($this->itemId)->update($data);
            $this->dispatch('toast-success', ['title' => __('Forma de pago modificada')]);
        } else {
            FormaDePago::create($data + ['estado_id' => FormaDePago::ESTADO_ACTIVO]);
            $this->dispatch('toast-success', ['title' => __('Forma de pago creada')]);
        }

        $this->dispatch('forma-de-pago-guardada');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.maestros.formas-de-pago.formulario');
    }
}

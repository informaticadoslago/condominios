<?php
namespace App\Livewire\Maestros\EntidadesBancarias;

use App\Models\EntidadBancaria;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public bool $abrir = false;
    public ?int $itemId = null;

    public string $codigo = '';
    public string $descripcion = '';
    public ?string $bic = null;

    protected function rules()
    {
        return [
            'codigo'      => ['required', 'string', 'max:10', Rule::unique('entidades_bancarias', 'codigo')->ignore($this->itemId)],
            'descripcion' => ['required', 'string', 'max:255'],
            'bic'         => ['nullable', 'string', 'max:11'],
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima longitud de :attribute = :max',
            'unique'   => 'Ya existe una entidad con ese código',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'codigo'      => __('código'),
            'descripcion' => __('descripción'),
            'bic'         => __('BIC'),
        ];
    }

    #[On('abrir-crear-entidad-bancaria')]
    public function crear()
    {
        $this->reset(['itemId', 'codigo', 'descripcion', 'bic']);
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('entidad-bancaria-editar')]
    public function editar($id)
    {
        $item = EntidadBancaria::find($id);
        if (! $item) {
            return;
        }
        $this->itemId      = $item->id;
        $this->codigo      = $item->codigo;
        $this->descripcion = $item->descripcion;
        $this->bic         = $item->bic;
        $this->resetValidation();
        $this->abrir = true;
    }

    public function guardar()
    {
        $data = $this->validate();
        $data['bic'] = $data['bic'] ?: null;

        if ($this->itemId) {
            EntidadBancaria::whereKey($this->itemId)->update($data);
            $this->dispatch('toast-success', ['title' => __('Entidad modificada')]);
        } else {
            EntidadBancaria::create($data + ['estado_id' => EntidadBancaria::ESTADO_ACTIVO]);
            $this->dispatch('toast-success', ['title' => __('Entidad creada')]);
        }

        $this->dispatch('entidad-bancaria-guardada');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.maestros.entidades-bancarias.formulario');
    }
}

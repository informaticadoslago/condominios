<?php

namespace App\Livewire\EmpresasContables;

use App\Models\EmpresaContable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public bool $abrir = false;
    public ?int $itemId = null;

    public string $cif = '';
    public string $razon_social = '';

    protected function rules()
    {
        return [
            'cif'          => ['required', 'string', 'max:20', Rule::unique('empresas_contables', 'cif')->ignore($this->itemId)],
            'razon_social' => ['required', 'string', 'max:150'],
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima longitud de :attribute = :max',
            'unique'   => 'Ya existe una empresa contable con ese CIF',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'cif'          => __('CIF'),
            'razon_social' => __('razón social'),
        ];
    }

    #[On('abrir-crear-empresa-contable')]
    public function crear()
    {
        $this->reset(['itemId', 'cif', 'razon_social']);
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('empresa-contable-editar')]
    public function editar($id)
    {
        $item = EmpresaContable::find($id);
        if (! $item) {
            return;
        }
        $this->itemId       = $item->id;
        $this->cif          = $item->cif;
        $this->razon_social = $item->razon_social;
        $this->resetValidation();
        $this->abrir = true;
    }

    public function guardar()
    {
        $data = $this->validate();

        if ($this->itemId) {
            $empresa = EmpresaContable::findOrFail($this->itemId);
            $empresa->update($data);
            $this->dispatch('toast-success', ['title' => __('Empresa contable modificada')]);
        } else {
            DB::transaction(fn () => EmpresaContable::create($data));
            $this->dispatch('toast-success', ['title' => __('Empresa contable creada')]);
        }

        $this->dispatch('empresa-contable-guardada');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.empresas-contables.formulario', [
            // Se resuelven solas al enlazar una comunidad (ver AsegurarTiposComisionBancaria);
            // aquí solo se enseñan, no se editan.
            'tiposComisionBancaria' => $this->itemId
                ? EmpresaContable::find($this->itemId)?->tiposComisionBancaria()->with('cuentaContable')->get()
                : collect(),
        ]);
    }
}

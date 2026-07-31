<?php

namespace App\Livewire\CuentasContables;

use App\Models\CuentaContable;
use App\Models\TipoCuentaContable;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public bool $abrir = false;
    public ?int $itemId = null;

    public string $codigo = '';
    public string $nombre = '';
    public ?int $tipo_cuenta_contable_id = null;

    protected function rules()
    {
        return [
            'codigo'                  => ['required', 'digits:8', Rule::unique('cuenta_contables', 'codigo')->ignore($this->itemId)],
            'nombre'                  => ['required', 'string', 'max:150'],
            'tipo_cuenta_contable_id' => ['required', 'exists:tipo_cuenta_contables,id'],
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima longitud de :attribute = :max',
            'digits'   => 'El :attribute debe tener exactamente :digits dígitos numéricos',
            'unique'   => 'Ya existe una cuenta con ese código',
            'exists'   => 'El :attribute seleccionado no es válido',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'codigo'                  => __('código'),
            'nombre'                  => __('nombre'),
            'tipo_cuenta_contable_id' => __('tipo'),
        ];
    }

    #[On('abrir-crear-cuenta-contable')]
    public function crear()
    {
        $this->reset(['itemId', 'codigo', 'nombre', 'tipo_cuenta_contable_id']);
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('cuenta-contable-editar')]
    public function editar($id)
    {
        $item = CuentaContable::find($id);
        if (! $item || $item->estado_id != CuentaContable::ESTADO_ACTIVO) {
            return;
        }
        $this->itemId                  = $item->id;
        $this->codigo                  = $item->codigo;
        $this->nombre                  = $item->nombre;
        $this->tipo_cuenta_contable_id = $item->tipo_cuenta_contable_id;
        $this->resetValidation();
        $this->abrir = true;
    }

    public function guardar()
    {
        $data = $this->validate();

        if ($this->itemId) {
            CuentaContable::whereKey($this->itemId)->update($data);
            $this->dispatch('toast-success', ['title' => __('Cuenta modificada')]);
        } else {
            CuentaContable::create($data + ['estado_id' => CuentaContable::ESTADO_ACTIVO]);
            $this->dispatch('toast-success', ['title' => __('Cuenta creada')]);
        }

        $this->dispatch('cuenta-contable-guardada');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.cuentas-contables.formulario', [
            'tipos' => TipoCuentaContable::orderBy('id')->get(),
        ]);
    }
}

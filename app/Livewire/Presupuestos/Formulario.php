<?php

namespace App\Livewire\Presupuestos;

use App\Models\Presupuesto;
use App\Models\TipoEstadoPresupuesto;
use App\Models\TipoPresupuesto;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public bool $abrir = false;
    public ?int $itemId = null;

    public string $nombre = '';
    public ?int $anho = null;

    /** De cuotas o de derrama, entero: decide contra qué cuenta de ingresos se cobra. */
    public int $tipo_presupuesto_id = TipoPresupuesto::CUOTAS;

    protected function rules()
    {
        return [
            'nombre'              => ['required', 'string', 'max:100'],
            'anho'                => ['required', 'integer', 'digits:4'],
            'tipo_presupuesto_id' => ['required', 'exists:tipo_presupuestos,id'],
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
            'nombre'              => __('nombre'),
            'anho'                => __('año'),
            'tipo_presupuesto_id' => __('tipo'),
        ];
    }

    #[On('abrir-crear-presupuesto')]
    public function crear()
    {
        $this->reset(['itemId', 'nombre', 'anho', 'tipo_presupuesto_id']);
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
        $this->itemId              = $item->id;
        $this->nombre              = $item->nombre;
        $this->anho                = $item->anho;
        $this->tipo_presupuesto_id = $item->tipo_presupuesto_id;
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
        return view('livewire.presupuestos.formulario', [
            'tipos' => TipoPresupuesto::orderBy('id')->pluck('descripcion', 'id'),
        ]);
    }
}

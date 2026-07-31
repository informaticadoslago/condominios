<?php

namespace App\Livewire\EjerciciosContables;

use App\Models\EjercicioContable;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public bool $abrir = false;

    // Fijada por sesión, nunca por el cliente: #[Locked] rechaza cualquier intento
    // de cambiarla desde el navegador.
    #[Locked]
    public ?int $comunidad_id = null;
    public string $nombre = '';
    public ?string $fecha_inicio = null;
    public ?string $fecha_fin = null;

    protected function rules()
    {
        return [
            'comunidad_id' => ['required', 'exists:comunidades,id'],
            'nombre'       => [
                'required', 'string', 'max:50',
                Rule::unique('ejercicio_contables', 'nombre')->where(fn ($q) => $q->where('comunidad_id', $this->comunidad_id)),
            ],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ];
    }

    protected function messages()
    {
        return [
            'required'         => 'Debe rellenar :attribute',
            'max'              => 'Máxima longitud de :attribute = :max',
            'exists'           => 'La :attribute seleccionada no es válida',
            'unique'           => 'Esa comunidad ya tiene un ejercicio con ese nombre',
            'after_or_equal'   => 'La fecha fin no puede ser anterior a la fecha inicio',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'comunidad_id' => __('comunidad'),
            'nombre'       => __('nombre'),
            'fecha_inicio' => __('fecha inicio'),
            'fecha_fin'    => __('fecha fin'),
        ];
    }

    #[On('abrir-crear-ejercicio-contable')]
    public function crear()
    {
        $this->reset(['comunidad_id', 'nombre', 'fecha_inicio', 'fecha_fin']);
        $this->resetValidation();
        $this->comunidad_id = session('comunidad_actual_id');
        $this->abrir        = true;
    }

    /** Sugerencia al elegir la fecha inicio: el 31 de diciembre de ese mismo año. Editable después. */
    public function updatedFechaInicio($value)
    {
        if ($value) {
            $this->fecha_fin = Carbon::parse($value)->endOfYear()->toDateString();
        }
    }

    public function guardar()
    {
        $this->comunidad_id = session('comunidad_actual_id');

        $data = $this->validate();

        EjercicioContable::create($data + ['cerrado' => false]);
        $this->dispatch('toast-success', ['title' => __('Ejercicio creado')]);

        $this->dispatch('ejercicio-contable-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.ejercicios-contables.formulario');
    }
}

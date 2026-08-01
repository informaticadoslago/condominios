<?php

namespace App\Livewire\Presupuestos;

use App\Models\Presupuesto;
use App\Models\TipoEstadoPresupuesto;
use App\Models\TipoPeriodicidadPago;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public bool $abrir = false;
    public ?int $itemId = null;

    public string $nombre = '';
    public ?int $anho = null;
    public ?string $fecha_primer_pago = null;
    public ?int $periodicidad_id = null;

    protected function rules()
    {
        return [
            'nombre'            => ['required', 'string', 'max:100'],
            'anho'              => ['required', 'integer', 'digits:4'],
            'fecha_primer_pago' => ['required', 'date'],
            'periodicidad_id'   => ['required', 'exists:tipo_periodicidad_pagos,id'],
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima longitud de :attribute = :max',
            'digits'   => 'El :attribute debe tener exactamente :digits dígitos',
            'exists'   => 'El :attribute seleccionado no es válido',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'nombre'            => __('nombre'),
            'anho'              => __('año'),
            'fecha_primer_pago' => __('fecha del primer pago'),
            'periodicidad_id'   => __('periodicidad'),
        ];
    }

    /** Fechas resultantes con la periodicidad y la fecha de primer pago elegidas ahora mismo. */
    public function getFechasPagoProperty(): array
    {
        if (! $this->periodicidad_id || ! $this->fecha_primer_pago) {
            return [];
        }

        $meses = TipoPeriodicidadPago::find($this->periodicidad_id)?->meses;
        if (! $meses) {
            return [];
        }

        $inicio = Carbon::parse($this->fecha_primer_pago);

        return collect(range(1, Presupuesto::numeroPagosPara($meses)))
            ->map(fn ($i) => $inicio->copy()->addMonthsNoOverflow(($i - 1) * $meses))
            ->all();
    }

    #[On('abrir-crear-presupuesto')]
    public function crear()
    {
        $this->reset(['itemId', 'nombre', 'anho', 'fecha_primer_pago', 'periodicidad_id']);
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
        $this->itemId            = $item->id;
        $this->nombre            = $item->nombre;
        $this->anho              = $item->anho;
        $this->fecha_primer_pago = $item->fecha_primer_pago?->toDateString();
        $this->periodicidad_id   = $item->periodicidad_id;
        $this->resetValidation();
        $this->abrir = true;
    }

    public function guardar()
    {
        $data = $this->validate();

        // El número de pagos no lo elige el usuario: sale de la periodicidad (ver
        // Presupuesto::numeroPagosPara).
        $data['numero_pagos'] = Presupuesto::numeroPagosPara(TipoPeriodicidadPago::find($data['periodicidad_id'])->meses);

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
            'periodicidades' => TipoPeriodicidadPago::activo()->orderBy('id')->get(),
        ]);
    }
}

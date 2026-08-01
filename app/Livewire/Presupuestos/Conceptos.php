<?php

namespace App\Livewire\Presupuestos;

use App\Models\GrupoDeReparto;
use App\Models\Presupuesto;
use App\Models\TipoPeriodicidadPago;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.foco')]
class Conceptos extends Component
{
    // Fijado al entrar por la ruta, nunca por el cliente.
    #[Locked]
    public int $presupuesto_id;

    /** [['_key', 'id', 'concepto', 'importe', 'grupo_de_reparto_id'], ...] */
    public array $conceptos = [];

    public ?int $periodicidad_id = null;
    public ?string $fecha_primer_pago = null;
    public ?int $numero_pagos = null;

    public function mount(Presupuesto $presupuesto): void
    {
        abort_if($presupuesto->comunidad_id != session('comunidad_actual_id'), 403);

        $this->presupuesto_id = $presupuesto->id;

        $this->conceptos = $presupuesto->conceptos->map(fn ($c) => [
            '_key'                => Str::random(10),
            'id'                  => $c->id,
            'concepto'            => $c->concepto,
            'importe'             => $c->importe,
            'grupo_de_reparto_id' => $c->grupo_de_reparto_id,
        ])->all();

        if (! $this->conceptos) {
            $this->conceptos = [$this->lineaVacia()];
        }

        $this->periodicidad_id   = $presupuesto->periodicidad_id;
        $this->fecha_primer_pago = $presupuesto->fecha_primer_pago?->toDateString();
        $this->numero_pagos      = $presupuesto->numero_pagos;
    }

    protected function presupuesto(): Presupuesto
    {
        return Presupuesto::findOrFail($this->presupuesto_id);
    }

    protected function lineaVacia(): array
    {
        // Sin puntos: la clave se usa como wire:key y un punto la rompería.
        return ['_key' => Str::random(10), 'id' => null, 'concepto' => '', 'importe' => null, 'grupo_de_reparto_id' => null];
    }

    /**
     * Al elegir periodicidad, sugiere cuántos pagos caben en un año (12 ÷ meses de la
     * periodicidad) — editable después: cambiar el número de pagos no toca el intervalo
     * entre ellos, solo cuántos hay (ver EjerciciosContables::updatedFechaInicio, mismo patrón).
     */
    public function updatedPeriodicidadId($value): void
    {
        $meses = TipoPeriodicidadPago::find($value)?->meses;
        $this->numero_pagos = $meses ? Presupuesto::numeroPagosPara($meses) : null;
    }

    /** [fecha, importe] por pago, con el total actual de los conceptos (aunque no se hayan guardado aún). */
    public function getPrevisionPagosProperty(): array
    {
        if (! $this->periodicidad_id || ! $this->fecha_primer_pago || ! $this->numero_pagos) {
            return [];
        }

        $meses = TipoPeriodicidadPago::find($this->periodicidad_id)?->meses;
        if (! $meses) {
            return [];
        }

        $total  = collect($this->conceptos)->sum(fn ($c) => (float) ($c['importe'] ?? 0));
        $inicio = Carbon::parse($this->fecha_primer_pago);
        $n      = $this->numero_pagos;
        $cuota  = $n > 0 ? round($total / $n, 2) : 0;

        return collect(range(1, $n))
            ->map(function ($i) use ($inicio, $meses, $n, $cuota, $total) {
                // El redondeo se ajusta en el primer pago.
                $importe = $i === 1 ? round($total - $cuota * ($n - 1), 2) : $cuota;

                return ['fecha' => $inicio->copy()->addMonthsNoOverflow(($i - 1) * $meses), 'importe' => $importe];
            })->all();
    }

    protected function rules()
    {
        return [
            'conceptos'                        => ['array'],
            // Individualmente nada es obligatorio: una línea puede quedar en blanco
            // (se descarta al guardar). withValidator() exige las 3 en cuanto se
            // rellena cualquiera de ellas, y que quede al menos una línea rellena.
            'conceptos.*.concepto'             => ['nullable', 'string', 'max:150'],
            'conceptos.*.importe'              => ['nullable', 'numeric', 'min:0.01'],
            'conceptos.*.grupo_de_reparto_id'  => [
                'nullable',
                Rule::exists('grupos_de_reparto', 'id')->where('comunidad_id', session('comunidad_actual_id')),
            ],
            'periodicidad_id'                  => ['nullable', 'exists:tipo_periodicidad_pagos,id'],
            'fecha_primer_pago'                => ['nullable', 'date', 'required_with:periodicidad_id'],
            'numero_pagos'                     => ['nullable', 'integer', 'min:1', 'required_with:periodicidad_id'],
        ];
    }

    protected function messages()
    {
        return [
            'required'      => 'Debe rellenar :attribute',
            'required_with' => 'Debe rellenar :attribute',
            'max'           => 'Máxima longitud de :attribute = :max',
            'numeric'       => ':attribute debe ser un número',
            'min'           => ':attribute debe ser mayor o igual a :min',
            'exists'        => 'El :attribute seleccionado no es válido',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'conceptos.*.concepto'            => __('concepto'),
            'conceptos.*.importe'             => __('importe'),
            'conceptos.*.grupo_de_reparto_id' => __('grupo de reparto'),
            'periodicidad_id'                 => __('periodicidad'),
            'fecha_primer_pago'               => __('fecha del primer pago'),
            'numero_pagos'                     => __('número de pagos'),
        ];
    }

    /** Una línea está en blanco cuando los 3 campos lo están. */
    protected function esLineaVacia(array $linea): bool
    {
        $concepto = trim((string) ($linea['concepto'] ?? ''));
        $importe  = $linea['importe'] ?? null;

        return $concepto === '' && ($importe === null || $importe === '') && empty($linea['grupo_de_reparto_id']);
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $rellenas = 0;

            foreach ($this->conceptos as $i => $linea) {
                if ($this->esLineaVacia($linea)) {
                    continue;
                }

                $rellenas++;

                if (trim((string) ($linea['concepto'] ?? '')) === '') {
                    $validator->errors()->add("conceptos.$i.concepto", __('Debe rellenar :attribute', ['attribute' => __('concepto')]));
                }
                if ($linea['importe'] === null || $linea['importe'] === '') {
                    $validator->errors()->add("conceptos.$i.importe", __('Debe rellenar :attribute', ['attribute' => __('importe')]));
                }
                if (empty($linea['grupo_de_reparto_id'])) {
                    $validator->errors()->add("conceptos.$i.grupo_de_reparto_id", __('Debe rellenar :attribute', ['attribute' => __('grupo de reparto')]));
                }
            }

            if ($rellenas === 0) {
                $validator->errors()->add('conceptos', __('El presupuesto debe tener al menos un concepto'));
            }
        });
    }

    public function agregarLinea(): void
    {
        $linea             = $this->lineaVacia();
        $this->conceptos[] = $linea;

        // El foco salta al campo "Concepto" de la línea recién creada (ver blade).
        $this->dispatch('concepto-linea-anadida', key: $linea['_key']);
    }

    public function quitarLinea(int $index): void
    {
        unset($this->conceptos[$index]);
        $this->conceptos = array_values($this->conceptos);
    }

    public function guardar()
    {
        $data = $this->validate();

        DB::transaction(function () use ($data) {
            $presupuesto = $this->presupuesto();

            $presupuesto->update([
                'periodicidad_id'   => $data['periodicidad_id'],
                'fecha_primer_pago' => $data['fecha_primer_pago'],
                'numero_pagos'      => $data['numero_pagos'],
            ]);

            // Sin histórico que preservar (a diferencia de los asientos contables): se
            // sustituyen todas las líneas por las que llegan del formulario.
            $presupuesto->conceptos()->delete();

            foreach ($data['conceptos'] as $linea) {
                if ($this->esLineaVacia($linea)) {
                    continue;
                }

                $presupuesto->conceptos()->create([
                    'concepto'            => $linea['concepto'],
                    'importe'             => $linea['importe'],
                    'grupo_de_reparto_id' => $linea['grupo_de_reparto_id'],
                ]);
            }
        });

        session()->flash('mensaje', __('Conceptos del presupuesto guardados'));

        return redirect()->route('presupuestos.index');
    }

    public function render()
    {
        return view('livewire.presupuestos.conceptos', [
            'presupuesto'    => $this->presupuesto(),
            'grupos'         => GrupoDeReparto::where('comunidad_id', session('comunidad_actual_id'))->orderBy('nombre')->get(),
            'periodicidades' => TipoPeriodicidadPago::activo()->orderBy('id')->get(),
        ]);
    }
}

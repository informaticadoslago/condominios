<?php

namespace App\Livewire\Presupuestos;

use App\Models\GrupoDeReparto;
use App\Models\Presupuesto;
use App\Models\TipoEstadoPresupuesto;
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

    /** Fechas finales propuestas de cada pago. Se usan para editar el plazo real. */
    public array $fechas_pago = [];

    /** Importe final propuesto de cada pago. Se usan para editar el importe real. */
    public array $importes_pago = [];

    /** Si el presupuesto ya está aprobado, los vencimientos y el marco de pagos quedan fijos. */
    public bool $bloqueado = false;

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

        $this->bloqueado = $presupuesto->estado_id == TipoEstadoPresupuesto::APROBADO;

        $this->periodicidad_id   = $presupuesto->periodicidad_id;
        $this->fecha_primer_pago = $presupuesto->fecha_primer_pago?->toDateString();
        $this->numero_pagos      = $presupuesto->numero_pagos;

        if ($presupuesto->fechas_pago !== null && $presupuesto->fechas_pago !== []) {
            $this->fechas_pago = array_values(array_map(fn ($fecha) => (string) $fecha, $presupuesto->fechas_pago));
        } elseif ($presupuesto->estado_id == TipoEstadoPresupuesto::APROBADO) {
            $this->fechas_pago = $presupuesto->recibos()
                ->orderBy('numero_pago')
                ->get()
                ->map(fn ($recibo) => $recibo->fecha_vencimiento?->toDateString())
                ->filter()
                ->values()
                ->all();
        } else {
            $this->sincronizarPrevisionFechas();
        }

        if ($presupuesto->importes_pago !== null && $presupuesto->importes_pago !== []) {
            $this->importes_pago = array_values(array_map(fn ($importe) => number_format((float) $importe, 2, '.', ''), $presupuesto->importes_pago));
        } elseif ($presupuesto->estado_id == TipoEstadoPresupuesto::APROBADO) {
            $this->importes_pago = $presupuesto->recibos()
                ->orderBy('numero_pago')
                ->get()
                ->map(fn ($recibo) => number_format((float) $recibo->importe, 2, '.', ''))
                ->values()
                ->all();
        } else {
            $this->sincronizarPrevisionImportes();
        }
    }

    protected function sincronizarPrevisionFechas(): void
    {
        if (! $this->periodicidad_id || ! $this->fecha_primer_pago || ! $this->numero_pagos) {
            $this->fechas_pago = [];

            return;
        }

        $meses = TipoPeriodicidadPago::find($this->periodicidad_id)?->meses;
        if (! $meses) {
            $this->fechas_pago = [];

            return;
        }

        $inicio = Carbon::parse($this->fecha_primer_pago);
        $this->fechas_pago = collect(range(1, (int) $this->numero_pagos))
            ->map(fn ($i) => $inicio->copy()->addMonthsNoOverflow(($i - 1) * $meses)->toDateString())
            ->values()
            ->all();
    }

    protected function sincronizarPrevisionImportes(): void
    {
        if (! $this->periodicidad_id || ! $this->fecha_primer_pago || ! $this->numero_pagos) {
            $this->importes_pago = [];

            return;
        }

        $total = collect($this->conceptos)->sum(fn ($c) => (float) ($c['importe'] ?? 0));
        $this->importes_pago = collect(Presupuesto::repartirPagos($total, (int) $this->numero_pagos))
            ->map(fn ($importe) => number_format($importe, 2, '.', ''))
            ->values()
            ->all();
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
        if ($this->bloqueado) {
            return;
        }

        $meses = TipoPeriodicidadPago::find($value)?->meses;
        $this->numero_pagos = $meses ? Presupuesto::numeroPagosPara($meses) : null;
        $this->sincronizarPrevisionFechas();
        $this->sincronizarPrevisionImportes();
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

        return collect(Presupuesto::repartirPagos($total, $this->numero_pagos))
            ->values()
            ->map(function ($importe, $i) use ($inicio, $meses) {
                $fecha = $this->fechas_pago[$i] ?? $inicio->copy()->addMonthsNoOverflow($i * $meses)->toDateString();

                return ['fecha' => Carbon::parse($fecha), 'importe' => $importe];
            })
            ->all();
    }

    public function updatedFechaPrimerPago($value): void
    {
        if ($this->bloqueado) {
            return;
        }

        $this->sincronizarPrevisionFechas();
        $this->sincronizarPrevisionImportes();
    }

    public function updatedNumeroPagos($value): void
    {
        if ($this->bloqueado) {
            return;
        }

        $this->sincronizarPrevisionFechas();
        $this->sincronizarPrevisionImportes();
    }

    public function updatedConceptos(): void
    {
        if ($this->bloqueado) {
            return;
        }

        $this->sincronizarPrevisionImportes();
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
            'fechas_pago'                      => ['nullable', 'array'],
            'fechas_pago.*'                    => ['required', 'date'],
            'importes_pago'                    => ['nullable', 'array'],
            'importes_pago.*'                  => ['required', 'numeric', 'min:0.01'],
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
            'fechas_pago.*'                   => __('fecha de pago'),
            'importes_pago.*'                 => __('importe de pago'),
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

            $totalPresupuesto = collect($this->conceptos)->sum(fn ($c) => (float) ($c['importe'] ?? 0));
            $totalCentimos    = (int) round($totalPresupuesto * 100);

            if (! empty($this->importes_pago) && ! empty($this->numero_pagos)) {
                $sumadosCentimos = 0;
                foreach ($this->importes_pago as $valor) {
                    $sumadosCentimos += (int) round((float) $valor * 100);
                }

                if ($sumadosCentimos !== $totalCentimos) {
                    $validator->errors()->add('importes_pago', __('La suma de los importes de pago debe ser exactamente el 100% del presupuesto: :total', ['total' => number_format($totalPresupuesto, 2, ',', '.') ]));
                }
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

            if (! $presupuesto->estado_id || $presupuesto->estado_id != TipoEstadoPresupuesto::APROBADO) {
                $presupuesto->update([
                    'periodicidad_id'   => $data['periodicidad_id'],
                    'fecha_primer_pago' => $data['fecha_primer_pago'],
                    'numero_pagos'      => $data['numero_pagos'],
                    'fechas_pago'       => $data['fechas_pago'] ?? [],
                    'importes_pago'     => $data['importes_pago'] ?? [],
                ]);
            }

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

            if ($presupuesto->estado_id == TipoEstadoPresupuesto::APROBADO) {
                $this->sincronizarFechasEnRecibos($presupuesto, $data['fechas_pago'] ?? []);
                $this->sincronizarImportesEnRecibos($presupuesto, $data['importes_pago'] ?? []);
            }
        });

        session()->flash('mensaje', __('Conceptos del presupuesto guardados'));

        return redirect()->route('presupuestos.index');
    }

    private function sincronizarFechasEnRecibos(Presupuesto $presupuesto, array $fechas): void
    {
        if ($fechas === []) {
            return;
        }

        $presupuesto->recibos()
            ->orderBy('numero_pago')
            ->get()
            ->each(function ($recibo) use ($fechas) {
                $indice = (int) $recibo->numero_pago - 1;
                if (isset($fechas[$indice])) {
                    $recibo->update([
                        'fecha_vencimiento' => $fechas[$indice],
                    ]);
                }
            });
    }

    private function sincronizarImportesEnRecibos(Presupuesto $presupuesto, array $importes): void
    {
        if ($importes === []) {
            return;
        }

        $presupuesto->recibos()
            ->orderBy('numero_pago')
            ->get()
            ->each(function ($recibo) use ($importes) {
                $indice = (int) $recibo->numero_pago - 1;
                if (isset($importes[$indice])) {
                    $recibo->update([
                        'importe' => round((float) $importes[$indice], 2),
                    ]);
                }
            });
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

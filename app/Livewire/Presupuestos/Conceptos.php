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

    /**
     * Porcentaje del total que representa cada pago. Es lo único que se edita: el
     * importe en euros no se puede fijar aquí porque depende de cómo lo reparta cada
     * grupo de reparto, algo que solo se sabe en la pantalla de Reparto.
     */
    public array $porcentajes_pago = [];

    /** Importe en euros de cada pago, calculado a partir de porcentajes_pago. Solo lectura. */
    public array $importes_pago = [];

    /**
     * Si el presupuesto ya está aprobado, los recibos ya se generaron con este reparto y
     * no se vuelven a tocar: periodicidad, fechas, porcentajes de pago, grupo de reparto
     * e importe de cada concepto quedan fijos (guardar() los ignora aunque lleguen
     * distintos). Solo el texto del concepto se puede seguir corrigiendo.
     */
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

        $total = collect($this->conceptos)->sum(fn ($c) => (float) ($c['importe'] ?? 0));

        if ($presupuesto->porcentajes_pago !== null && $presupuesto->porcentajes_pago !== []) {
            $this->porcentajes_pago = array_values(array_map(fn ($pct) => number_format((float) $pct, 2, '.', ''), $presupuesto->porcentajes_pago));
        } elseif ($presupuesto->estado_id == TipoEstadoPresupuesto::APROBADO) {
            // Reconstruye qué porcentaje representó cada pago ya generado, sumando los
            // recibos de todos los inmuebles para ese número de pago.
            $totalesPorPago = $presupuesto->recibos()
                ->orderBy('numero_pago')
                ->get()
                ->groupBy('numero_pago')
                ->map(fn ($recibos) => (float) $recibos->sum('importe'))
                ->values();

            $this->porcentajes_pago = $total > 0
                ? $totalesPorPago->map(fn ($importe) => number_format($importe / $total * 100, 2, '.', ''))->all()
                : [];
        } else {
            $this->sincronizarPrevisionPorcentajes();
        }

        $this->recalcularImportesPago($total);
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

    /**
     * Porcentaje igualado por defecto entre todos los pagos (editable después). No se
     * puede sugerir un importe en euros aquí: depende de cómo lo reparta cada grupo de
     * reparto, algo que solo se ve en la pantalla de Reparto.
     */
    protected function sincronizarPrevisionPorcentajes(): void
    {
        if (! $this->periodicidad_id || ! $this->fecha_primer_pago || ! $this->numero_pagos) {
            $this->porcentajes_pago = [];

            return;
        }

        $this->porcentajes_pago = collect(Presupuesto::repartirPagos(100, (int) $this->numero_pagos))
            ->map(fn ($pct) => number_format($pct, 2, '.', ''))
            ->values()
            ->all();
    }

    /** Importe en euros de cada pago = su porcentaje sobre el total actual de conceptos. Solo lectura. */
    protected function recalcularImportesPago(?float $total = null): void
    {
        $total ??= collect($this->conceptos)->sum(fn ($c) => (float) ($c['importe'] ?? 0));

        if (empty($this->porcentajes_pago)) {
            $this->importes_pago = [];

            return;
        }

        $pesos = array_map(fn ($pct) => (float) $pct, $this->porcentajes_pago);
        $this->importes_pago = collect(Presupuesto::repartirProporcional($total, $pesos))
            ->map(fn ($importe) => number_format($importe, 2, '.', ''))
            ->values()
            ->all();
    }

    /** Normaliza a 2 decimales para comparar porcentajes venidos de sitios distintos (BD vs formulario). */
    protected function porcentajesNormalizados(?array $porcentajes): array
    {
        return array_map(fn ($pct) => number_format((float) $pct, 2, '.', ''), $porcentajes ?? []);
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
        $this->sincronizarPrevisionPorcentajes();
        $this->recalcularImportesPago();
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

        $inicio = Carbon::parse($this->fecha_primer_pago);

        return collect(range(0, $this->numero_pagos - 1))
            ->map(function ($i) use ($inicio, $meses) {
                $fecha = $this->fechas_pago[$i] ?? $inicio->copy()->addMonthsNoOverflow($i * $meses)->toDateString();

                return ['fecha' => Carbon::parse($fecha), 'importe' => $this->importes_pago[$i] ?? 0];
            })
            ->all();
    }

    public function updatedFechaPrimerPago($value): void
    {
        if ($this->bloqueado) {
            return;
        }

        $this->sincronizarPrevisionFechas();
        $this->sincronizarPrevisionPorcentajes();
        $this->recalcularImportesPago();
    }

    public function updatedNumeroPagos($value): void
    {
        if ($this->bloqueado) {
            return;
        }

        $this->sincronizarPrevisionFechas();
        $this->sincronizarPrevisionPorcentajes();
        $this->recalcularImportesPago();
    }

    public function updatedConceptos(): void
    {
        if ($this->bloqueado) {
            return;
        }

        // Los porcentajes elegidos no cambian solo porque cambie el total: se
        // recalculan los euros de cada pago sobre ese porcentaje.
        $this->recalcularImportesPago();
    }

    public function updatedPorcentajesPago(): void
    {
        if ($this->bloqueado) {
            return;
        }

        $this->recalcularImportesPago();
    }

    protected function rules()
    {
        $reglas = [
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
        ];

        // Bloqueado, estos campos son de solo lectura (recalculados en mount() a partir
        // de los recibos ya emitidos): no se validan, para no bloquear la corrección del
        // texto de un concepto por un desajuste de céntimos ajeno a lo que se guarda.
        if (! $this->bloqueado) {
            $reglas += [
                'fecha_primer_pago'   => ['nullable', 'date', 'required_with:periodicidad_id'],
                'numero_pagos'        => ['nullable', 'integer', 'min:1', 'required_with:periodicidad_id'],
                'fechas_pago'         => ['nullable', 'array'],
                'fechas_pago.*'       => ['required', 'date'],
                'porcentajes_pago'    => ['nullable', 'array'],
                'porcentajes_pago.*'  => ['required', 'numeric', 'min:0.01', 'max:100'],
            ];
        }

        return $reglas;
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
            'porcentajes_pago.*'              => __('porcentaje de pago'),
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

            if (! $this->bloqueado && ! empty($this->porcentajes_pago) && ! empty($this->numero_pagos)) {
                $sumadosCentesimas = 0;
                foreach ($this->porcentajes_pago as $valor) {
                    $sumadosCentesimas += (int) round((float) $valor * 100);
                }

                if ($sumadosCentesimas !== 10000) {
                    $validator->errors()->add('porcentajes_pago', __('La suma de los porcentajes de pago debe ser exactamente 100%'));
                }
            }
        });
    }

    public function agregarLinea(): void
    {
        if ($this->bloqueado) {
            return;
        }

        $linea             = $this->lineaVacia();
        $this->conceptos[] = $linea;

        // El foco salta al campo "Concepto" de la línea recién creada (ver blade).
        $this->dispatch('concepto-linea-anadida', key: $linea['_key']);
    }

    public function quitarLinea(int $index): void
    {
        if ($this->bloqueado) {
            return;
        }

        unset($this->conceptos[$index]);
        $this->conceptos = array_values($this->conceptos);
    }

    public function guardar()
    {
        $data = $this->validate();

        DB::transaction(function () use ($data) {
            $presupuesto = $this->presupuesto();

            // Periodicidad, fecha del primer pago, número de pagos, fechas y porcentajes
            // de cada pago quedan fijos en cuanto se aprueba: los recibos ya generados a
            // partir de ellos no se tocan más.
            if (! $this->bloqueado) {
                // Si el reparto (pantalla de Reparto) estaba fijado a mano y esto cambia
                // la base sobre la que se fijó, deja de tener sentido: se desfija sin
                // preguntar, no se avisa ni se bloquea el guardado de conceptos.
                $cambiaReparto = $presupuesto->periodicidad_id != $data['periodicidad_id']
                    || optional($presupuesto->fecha_primer_pago)->toDateString() != $data['fecha_primer_pago']
                    || $presupuesto->numero_pagos != $data['numero_pagos']
                    || $this->porcentajesNormalizados($presupuesto->porcentajes_pago) != $this->porcentajesNormalizados($data['porcentajes_pago'] ?? []);

                $presupuesto->update([
                    'periodicidad_id'   => $data['periodicidad_id'],
                    'fecha_primer_pago' => $data['fecha_primer_pago'],
                    'numero_pagos'      => $data['numero_pagos'],
                    'fechas_pago'       => $data['fechas_pago'] ?? [],
                    'porcentajes_pago'  => $data['porcentajes_pago'] ?? [],
                ]);

                if ($cambiaReparto && $presupuesto->fijado) {
                    $presupuesto->desfijar();
                }
            }

            if ($this->bloqueado) {
                // Aprobado, el reparto ya está fijado: solo se puede corregir el texto
                // de un concepto existente, nunca su importe, su grupo de reparto, ni
                // añadir o quitar líneas (cambiaría lo que ya se cobró en los recibos).
                $originales = $presupuesto->conceptos()->get()->keyBy('id');

                foreach ($data['conceptos'] as $linea) {
                    if (empty($linea['id']) || ! $originales->has($linea['id'])) {
                        continue;
                    }

                    $originales[$linea['id']]->update(['concepto' => $linea['concepto']]);
                }
            } else {
                // Sin histórico que preservar (a diferencia de los asientos contables):
                // se sustituyen todas las líneas por las que llegan del formulario.
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

<?php

namespace App\Livewire\Presupuestos;

use App\Exceptions\RecibosNoGenerablesException;
use App\Models\Presupuesto;
use App\Models\TipoEstadoPresupuesto;
use App\Services\Presupuestos\CalculadorReparto;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class Reparto extends Component
{
    public int $presupuesto_id;

    /** [inmueble_id => [importe_pago_1, importe_pago_2, ...]], solo si el reparto está fijado y no aprobado. */
    public array $pagosEditados = [];

    public function mount(Presupuesto $presupuesto): void
    {
        abort_if($presupuesto->comunidad_id != session('comunidad_actual_id'), 403);

        $this->presupuesto_id = $presupuesto->id;

        if ($presupuesto->fijado && $presupuesto->estado_id != TipoEstadoPresupuesto::APROBADO) {
            $this->cargarPagosEditados($presupuesto);
        }
    }

    private function presupuesto(): Presupuesto
    {
        $presupuesto = Presupuesto::findOrFail($this->presupuesto_id);

        abort_if($presupuesto->comunidad_id != session('comunidad_actual_id'), 403);

        return $presupuesto;
    }

    private function cargarPagosEditados(Presupuesto $presupuesto): void
    {
        $reparto = app(CalculadorReparto::class)->calcular($presupuesto);

        $this->pagosEditados = $reparto['global']->mapWithKeys(
            fn ($fila) => [$fila['inmueble']->id => array_map(
                fn ($importe) => number_format($importe, 2, '.', ''),
                $fila['pagos']
            )]
        )->all();
    }

    public function confirmarFijar(): void
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Fijar el reparto?'),
            'text'               => __('El importe de cada inmueble y pago deja de recalcularse aunque cambien los conceptos, y podrás corregirlo a mano. No se generan recibos ni se aprueba el presupuesto.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#16a34a',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, fijar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarFijar',
            'cancelCallback'     => 'fijarCancelado',
            'id'                 => $this->presupuesto_id,
        ]);
    }

    #[On('ejecutarFijar')]
    public function fijar(): void
    {
        $presupuesto = $this->presupuesto();

        if ($presupuesto->fijado || $presupuesto->estado_id == TipoEstadoPresupuesto::APROBADO) {
            return;
        }

        $reparto = app(CalculadorReparto::class)->calcular($presupuesto);

        if (! $reparto['datosPagoCompletos'] || $reparto['global']->isEmpty()) {
            $this->dispatch('toast-error', ['title' => __('Faltan datos de pago o inmuebles con reparto: no se puede fijar.')]);

            return;
        }

        $presupuesto->update([
            'fijado'         => true,
            'reparto_fijado' => $reparto['global']->mapWithKeys(
                fn ($fila) => [$fila['inmueble']->id => $fila['pagos']]
            )->all(),
        ]);

        $this->cargarPagosEditados($presupuesto);

        $this->dispatch('toast-success', ['title' => __('Reparto fijado')]);
    }

    #[On('fijarCancelado')]
    public function fijarCancelado($id = null): void
    {
        // el usuario canceló; no hacemos nada
    }

    public function confirmarDesfijar(): void
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Desfijar el reparto?'),
            'text'               => __('Se descartan los importes fijados a mano y se vuelve a calcular en vivo a partir de los conceptos y porcentajes actuales.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#dc2626',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, desfijar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarDesfijar',
            'cancelCallback'     => 'desfijarCancelado',
            'id'                 => $this->presupuesto_id,
        ]);
    }

    #[On('ejecutarDesfijar')]
    public function desfijar(): void
    {
        $presupuesto = $this->presupuesto();

        if (! $presupuesto->fijado || $presupuesto->estado_id == TipoEstadoPresupuesto::APROBADO) {
            return;
        }

        $presupuesto->desfijar();
        $this->pagosEditados = [];

        $this->dispatch('toast-success', ['title' => __('Reparto desfijado')]);
    }

    #[On('desfijarCancelado')]
    public function desfijarCancelado($id = null): void
    {
        // el usuario canceló; no hacemos nada
    }

    /** Persiste a mano lo editado en `pagosEditados`. No valida que cuadre: el descuadre solo se avisa en rojo. */
    public function guardarReparto(): void
    {
        $presupuesto = $this->presupuesto();

        if (! $presupuesto->fijado || $presupuesto->estado_id == TipoEstadoPresupuesto::APROBADO) {
            return;
        }

        $presupuesto->update([
            'reparto_fijado' => collect($this->pagosEditados)
                ->map(fn ($pagos) => array_map(fn ($importe) => round((float) $importe, 2), $pagos))
                ->all(),
        ]);

        $this->dispatch('toast-success', ['title' => __('Reparto guardado')]);
    }

    public function confirmarAprobar(): void
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Aprobar el presupuesto?'),
            'text'               => __('Se generarán los recibos de todos los inmuebles con este reparto. El reparto queda fijado y no se podrá volver atrás.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#16a34a',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, aprobar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarAprobar',
            'cancelCallback'     => 'aprobarCancelado',
            'id'                 => $this->presupuesto_id,
        ]);
    }

    #[On('ejecutarAprobar')]
    public function aprobar(): void
    {
        $presupuesto = $this->presupuesto();

        if ($presupuesto->estado_id == TipoEstadoPresupuesto::APROBADO) {
            return;
        }

        // Si está fijado, los recibos van a salir de reparto_fijado tal cual está en
        // BD (ver CalculadorReparto::calcular()): antes de aprobar hay que comprobar
        // que sigue cuadrando con el total de cada inmueble, no recalcularlo.
        if ($presupuesto->fijado) {
            $reparto    = app(CalculadorReparto::class)->calcular($presupuesto);
            $descuadres = $this->descuadres($reparto['global']);

            if ($descuadres !== []) {
                $this->dispatch('toast-error', [
                    'title' => __('El reparto fijado no cuadra con el total en: :inmuebles', ['inmuebles' => implode(', ', $descuadres)]),
                ]);

                return;
            }
        }

        try {
            // El cambio de estado va dentro de la transacción a propósito: si la
            // generación de recibos falla (un inmueble sin propietario o sin forma de
            // pago), el presupuesto NO puede quedarse aprobado y sin recibos — desde
            // ese estado ya no volvería a dispararse la generación.
            DB::transaction(function () use ($presupuesto) {
                $presupuesto->update(['estado_id' => TipoEstadoPresupuesto::APROBADO]);
            });
        } catch (RecibosNoGenerablesException $e) {
            $this->dispatch('toast-error', ['title' => $e->getMessage()]);

            return;
        }

        $this->dispatch('toast-success', ['title' => __('Presupuesto aprobado y recibos generados')]);
    }

    #[On('aprobarCancelado')]
    public function aprobarCancelado($id = null): void
    {
        // el usuario canceló; no hacemos nada
    }

    /** Nombres de los inmuebles cuya suma de pagos no coincide (al céntimo) con su total. */
    private function descuadres($global): array
    {
        return $global
            ->filter(fn ($fila) => (int) round((array_sum($fila['pagos']) - $fila['total']) * 100) !== 0)
            ->map(fn ($fila) => trim(($fila['inmueble']->planta ?? '').' '.($fila['inmueble']->puerta ?? '')) ?: "#{$fila['inmueble']->id}")
            ->values()
            ->all();
    }

    public function render(CalculadorReparto $calculador)
    {
        $presupuesto = Presupuesto::with(['estado', 'periodicidad', 'conceptos.grupoDeReparto.inmuebles'])
            ->findOrFail($this->presupuesto_id);

        // El cálculo vive en el servicio porque el generador de recibos vuelca justo
        // esto al aprobar: los dos tienen que dar lo mismo siempre.
        $reparto = $calculador->calcular($presupuesto);

        $aprobado = $presupuesto->estado_id == TipoEstadoPresupuesto::APROBADO;

        return view('livewire.presupuestos.reparto', [
            'presupuesto'        => $presupuesto,
            'aprobado'           => $aprobado,
            'fijado'             => $presupuesto->fijado,
            // Editable: fijado pero todavía no aprobado (aprobado, los recibos ya
            // generados con esto no se pueden variar).
            'editable'           => $presupuesto->fijado && ! $aprobado,
            // Se resuelve aquí y no en la vista: dentro de un atributo de componente
            // Blade, una expresión con «->» corta la etiqueta en su «>» y descuadra la
            // plantilla entera.
            'puedeAprobar'       => $reparto['datosPagoCompletos'] && $reparto['global']->isNotEmpty(),
            'puedeFijar'         => ! $presupuesto->fijado && ! $aprobado && $reparto['datosPagoCompletos'] && $reparto['global']->isNotEmpty(),
            'datosPagoCompletos' => $reparto['datosPagoCompletos'],
            'totalPresupuesto'   => $reparto['total'],
            'grupos'             => $reparto['grupos'],
            'global'             => $reparto['global'],
            'fechasPagos'        => $reparto['fechasPagos'],
        ]);
    }
}

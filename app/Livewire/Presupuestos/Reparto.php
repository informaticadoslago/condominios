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

    public function mount(Presupuesto $presupuesto): void
    {
        abort_if($presupuesto->comunidad_id != session('comunidad_actual_id'), 403);

        $this->presupuesto_id = $presupuesto->id;
    }

    private function presupuesto(): Presupuesto
    {
        $presupuesto = Presupuesto::findOrFail($this->presupuesto_id);

        abort_if($presupuesto->comunidad_id != session('comunidad_actual_id'), 403);

        return $presupuesto;
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

    public function render(CalculadorReparto $calculador)
    {
        $presupuesto = Presupuesto::with(['estado', 'periodicidad', 'conceptos.grupoDeReparto.inmuebles'])
            ->findOrFail($this->presupuesto_id);

        // El cálculo vive en el servicio porque el generador de recibos vuelca justo
        // esto al aprobar: los dos tienen que dar lo mismo siempre.
        $reparto = $calculador->calcular($presupuesto);

        return view('livewire.presupuestos.reparto', [
            'presupuesto'        => $presupuesto,
            'aprobado'           => $presupuesto->estado_id == TipoEstadoPresupuesto::APROBADO,
            // Se resuelve aquí y no en la vista: dentro de un atributo de componente
            // Blade, una expresión con «->» corta la etiqueta en su «>» y descuadra la
            // plantilla entera.
            'puedeAprobar'       => $reparto['datosPagoCompletos'] && $reparto['global']->isNotEmpty(),
            'datosPagoCompletos' => $reparto['datosPagoCompletos'],
            'totalPresupuesto'   => $reparto['total'],
            'grupos'             => $reparto['grupos'],
            'global'             => $reparto['global'],
            'fechasPagos'        => $reparto['fechasPagos'],
        ]);
    }
}

<?php

namespace App\Livewire\Facturas;

use App\Exceptions\AsientoInvalidoException;
use App\Exceptions\CuentaContableDesconocidaException;
use App\Exceptions\EjercicioCerradoException;
use App\Exceptions\EjercicioContableDesconocidoException;
use App\Models\FacturaProveedor;
use App\Services\Facturas\RegistrarPagoFactura;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Registra el pago de una factura de proveedor: fecha e importe, que por defecto es todo
 * lo que queda pendiente. Admite pagos parciales, así que se puede abrir varias veces
 * sobre la misma factura hasta saldarla.
 */
class PagarFactura extends Component
{
    public bool $abrir = false;

    public ?int $facturaId = null;

    public string $proveedor = '';
    public float $pendiente  = 0;

    public $fecha;
    public $importe;

    #[On('abrir-pagar-factura')]
    public function abrir($id)
    {
        $factura = FacturaProveedor::with('proveedor.persona')->find($id);
        if (! $factura) {
            return;
        }

        $this->facturaId = $factura->id;
        $this->proveedor = $factura->proveedor?->persona?->razon_social
            ?: (string) $factura->proveedor?->persona?->nombre_completo;
        $this->pendiente = $factura->pendiente();
        $this->fecha     = now()->format('Y-m-d');
        $this->importe   = number_format($this->pendiente, 2, '.', '');

        $this->resetValidation();
        $this->abrir = true;
    }

    public function guardar(RegistrarPagoFactura $pagos)
    {
        $this->validate([
            'fecha'   => ['required', 'date'],
            'importe' => ['required', 'numeric', 'min:0.01', 'max:'.$this->pendiente],
        ], [], [
            'fecha'   => __('fecha'),
            'importe' => __('importe'),
        ]);

        $factura = FacturaProveedor::with('proveedor.persona.comunidad')->find($this->facturaId);
        if (! $factura) {
            return;
        }

        if ($motivo = $pagos->motivoNoPagable($factura)) {
            $this->dispatch('toast-error', ['title' => $motivo]);

            return;
        }

        try {
            $pago = $pagos->registrar($factura->id, $this->fecha, (float) $this->importe);
        } catch (AsientoInvalidoException|EjercicioCerradoException|EjercicioContableDesconocidoException|CuentaContableDesconocidaException $e) {
            // El pago quedó registrado; lo que falló es su asiento. Se dice tal cual, que
            // no es lo mismo que no haber pagado.
            $this->dispatch('toast-error', ['title' => __('Pago registrado, pero sin contabilizar: ').$e->getMessage()]);
            $this->cerrar();

            return;
        }

        $this->dispatch('toast-'.($pago ? 'success' : 'error'), [
            'title' => $pago ? __('Pago registrado') : __('No había nada que pagar'),
        ]);

        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir     = false;
        $this->facturaId = null;

        $this->dispatch('factura-pagada');
    }

    public function render()
    {
        return view('livewire.facturas.pagar-factura');
    }
}

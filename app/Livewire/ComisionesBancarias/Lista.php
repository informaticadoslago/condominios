<?php

namespace App\Livewire\ComisionesBancarias;

use App\Livewire\ListaComponent;
use App\Models\ComisionBancaria;
use App\Models\Comunidad;
use App\Services\ComisionesBancarias\DeshacerComisionBancaria;
use App\Services\ComisionesBancarias\EnlazarComisionesBancariasContabilidad;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    /** Índices de comisiones con las líneas desplegadas en la tabla. */
    public array $expandido = [];

    #[On('comision-bancaria-importada')]
    public function refrescar(): void
    {
        // el evento fuerza el re-render de la lista
    }

    public function mount()
    {
        $this->sort      = 'fecha';
        $this->direction = 'desc';
    }

    protected function columnasOrdenables(): ?array
    {
        return ['fecha', 'concepto'];
    }

    public function columnasDisponibles(): array
    {
        return [
            'fecha'         => __('Fecha'),
            'tipo'          => __('Tipo'),
            'cuenta'        => __('Cuenta bancaria'),
            'remesa'        => __('Remesa'),
            'concepto'      => __('Concepto'),
            'referencia'    => __('Referencia'),
            'importe'       => __('Importe'),
            'contabilizada' => __('Contabilizada'),
        ];
    }

    public function toggleDetalle(int $id): void
    {
        if (in_array($id, $this->expandido, true)) {
            $this->expandido = array_values(array_diff($this->expandido, [$id]));
        } else {
            $this->expandido[] = $id;
        }
    }

    /**
     * Reintenta el asiento de una comisión que se quedó sin contabilizar (cuenta que
     * faltaba, ejercicio cerrado en su momento...). Se puede repetir sin duplicar nada:
     * la referencia del asiento lo impide.
     */
    public function contabilizar(int $id): void
    {
        $comision = ComisionBancaria::where('id', $id)->whereNull('asiento_contable')->first();

        if (! $comision) {
            return;
        }

        $this->dispatch('swalConfirm', [
            'title'              => __('Contabilizar la comisión'),
            'text'               => __('Se generará el asiento en contabilidad.'),
            'icon'               => 'question',
            'showCancelButton'   => true,
            'focusConfirm'       => true,
            'confirmButtonColor' => '#16a34a',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, contabilizar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'contabilizar-comision-confirmada',
            'cancelCallback'     => 'contabilizar-comision-cancelada',
            'id'                 => $comision->id,
        ]);
    }

    #[On('contabilizar-comision-cancelada')]
    public function contabilizarCancelada($id = null): void
    {
        // el usuario canceló; no hacemos nada
    }

    #[On('contabilizar-comision-confirmada')]
    public function contabilizarConfirmada($id, EnlazarComisionesBancariasContabilidad $enlazar): void
    {
        $resultado = $enlazar->ejecutar([$id]);

        $this->dispatch($resultado['enlazadas'] > 0 ? 'toast-success' : 'toast-error', [
            'title' => $resultado['enlazadas'] > 0
                ? __('Comisión contabilizada')
                : __('Sigue sin poderse contabilizar: falta la cuenta de gasto o la subcuenta bancaria'),
        ]);
    }

    /**
     * Borra una comisión mal tecleada (fecha, importe, cuenta...) y su asiento, para
     * poder repetirla bien. No es para deshacer un cargo que de verdad ocurrió.
     */
    public function deshacer(int $id): void
    {
        $comision = $this->comisionDeLaComunidad($id);

        if (! $comision) {
            return;
        }

        $this->dispatch('swalConfirm', [
            'title'              => __('¿Deshacer la comisión?'),
            'text'               => __('Se borra la comisión y, si ya estaba contabilizada, su asiento. Solo tiene sentido si se tecleó mal.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, deshacer'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'deshacer-comision-confirmada',
            'cancelCallback'     => 'deshacer-comision-cancelada',
            'id'                 => $comision->id,
        ]);
    }

    #[On('deshacer-comision-cancelada')]
    public function deshacerCancelada($id = null): void
    {
        // el usuario canceló; no hacemos nada
    }

    #[On('deshacer-comision-confirmada')]
    public function deshacerConfirmada($id, DeshacerComisionBancaria $servicio): void
    {
        $comision = $this->comisionDeLaComunidad((int) $id);

        if (! $comision) {
            return;
        }

        $servicio->ejecutar($comision);

        $this->dispatch('toast-success', ['title' => __('Comisión deshecha')]);
    }

    /** Solo de la comunidad activa: mismo scope que la lista, para no poder deshacer la de otra. */
    private function comisionDeLaComunidad(int $id): ?ComisionBancaria
    {
        return ComisionBancaria::where('id', $id)
            ->whereHas('cuentaBancaria', fn ($q) => $q->where('titular_type', Comunidad::class)
                ->where('titular_id', session('comunidad_actual_id')))
            ->first();
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = $this->aplicarFiltros(
            ComisionBancaria::with(['cuentaBancaria', 'remesa', 'lineas', 'tipoComisionBancaria'])
                ->whereHas('cuentaBancaria', fn ($q) => $q->where('titular_type', Comunidad::class)
                    ->where('titular_id', session('comunidad_actual_id')))
                ->withSum('lineas', 'importe')
        )
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2->where('concepto', 'like', "%{$search}%")
                ->orWhere('referencia', 'like', "%{$search}%")))
            ->orderBy($this->sort, $this->direction)
            ->orderBy('id', 'desc')
            ->paginate($this->lineasXPagina);

        return view('livewire.comisiones-bancarias.lista', compact('items'));
    }
}

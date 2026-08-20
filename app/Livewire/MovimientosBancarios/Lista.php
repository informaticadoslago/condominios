<?php

namespace App\Livewire\MovimientosBancarios;

use App\Livewire\ListaComponent;
use App\Models\Comunidad;
use App\Models\CuentaBancaria;
use App\Models\MovimientoBancario;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

class Lista extends ListaComponent
{
    /** La lista siempre está filtrada a una cuenta bancaria: no se mezclan movimientos
     *  de varias, así que no hace falta columna "Cuenta bancaria" en la tabla. */
    #[Url]
    public ?int $cuentaBancariaId = null;

    /** Movimiento marcado para convertir en comisión bancaria. */
    public ?int $seleccionado = null;

    #[On('movimiento-bancario-importado')]
    public function refrescar(): void
    {
        // el evento fuerza el re-render de la lista
    }

    #[On('comision-bancaria-importada')]
    public function comisionRegistrada(): void
    {
        $this->seleccionado = null;
    }

    public function convertirEnComision(): void
    {
        if (! $this->seleccionado) {
            return;
        }

        $this->dispatch('abrir-convertir-en-comision', movimientoId: $this->seleccionado);
    }

    public function mount()
    {
        $this->sort      = 'fecha_valor';
        $this->direction = 'desc';

        if (! $this->cuentaBancariaId || ! $this->cuentaBancariaDeLaComunidad($this->cuentaBancariaId)) {
            $this->cuentaBancariaId = $this->cuentasBancariasComunidad()->first()?->id;
        }
    }

    protected function columnasOrdenables(): ?array
    {
        return ['fecha_valor', 'fecha_contable', 'fecha_operacion'];
    }

    public function columnasDisponibles(): array
    {
        return [
            'fecha_valor'     => __('F. Valor'),
            'fecha_contable'  => __('F. Contable'),
            'fecha_operacion' => __('F. Operación'),
            'tipo'            => __('Tipo'),
            'descripcion'     => __('Descripción'),
            'referencia'      => __('Referencia'),
            'importe'         => __('Importe'),
            'saldo'           => __('Saldo'),
        ];
    }

    /** Si se manipula el valor a mano (wire:model), se descarta en vez de enseñar la cuenta de otra comunidad. */
    public function updatedCuentaBancariaId($value): void
    {
        if (! $this->cuentaBancariaDeLaComunidad((int) $value)) {
            $this->cuentaBancariaId = $this->cuentasBancariasComunidad()->first()?->id;
        }

        $this->resetPage();
    }

    /** Cuentas bancarias de la comunidad activa, para el selector. */
    public function cuentasBancariasComunidad()
    {
        return CuentaBancaria::where('titular_type', Comunidad::class)
            ->where('titular_id', session('comunidad_actual_id'))
            ->orderBy('alias')
            ->get();
    }

    private function cuentaBancariaDeLaComunidad(int $id): ?CuentaBancaria
    {
        return $this->cuentasBancariasComunidad()->firstWhere('id', $id);
    }

    /**
     * Borra un movimiento mal importado. Si se reprocesa el mismo CSV/Q43, ese
     * movimiento vuelve a entrar (el hash ya no existe en la tabla).
     */
    public function borrar(int $id): void
    {
        $movimiento = $this->movimientoDeLaComunidad($id);

        if (! $movimiento) {
            return;
        }

        $this->dispatch('swalConfirm', [
            'title'              => __('¿Borrar el movimiento?'),
            'text'               => __('Si se vuelve a importar el mismo fichero, este movimiento se vuelve a dar de alta.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, borrar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'borrar-movimiento-bancario-confirmado',
            'cancelCallback'     => 'borrar-movimiento-bancario-cancelado',
            'id'                 => $movimiento->id,
        ]);
    }

    #[On('borrar-movimiento-bancario-cancelado')]
    public function borrarCancelado($id = null): void
    {
        // el usuario canceló; no hacemos nada
    }

    #[On('borrar-movimiento-bancario-confirmado')]
    public function borrarConfirmado($id): void
    {
        $movimiento = $this->movimientoDeLaComunidad((int) $id);

        if (! $movimiento) {
            return;
        }

        $movimiento->delete();

        $this->dispatch('toast-success', ['title' => __('Movimiento borrado')]);
    }

    /** Solo de la comunidad activa: mismo scope que la lista, para no poder borrar el de otra. */
    private function movimientoDeLaComunidad(int $id): ?MovimientoBancario
    {
        return MovimientoBancario::where('id', $id)
            ->whereHas('cuentaBancaria', fn ($q) => $q->where('titular_type', Comunidad::class)
                ->where('titular_id', session('comunidad_actual_id')))
            ->first();
    }

    public function render()
    {
        $search = trim($this->search ?? '');
        $cuentasBancarias = $this->cuentasBancariasComunidad();

        $items = $this->cuentaBancariaId
            ? $this->aplicarFiltros(MovimientoBancario::where('cuenta_bancaria_id', $this->cuentaBancariaId))
                ->when($search, fn ($q) => $q->where(fn ($q2) => $q2->where('descripcion', 'like', "%{$search}%")
                    ->orWhere('referencia', 'like', "%{$search}%")))
                ->orderBy($this->sort, $this->direction)
                ->orderBy('id', 'desc')
                ->paginate($this->lineasXPagina)
            : MovimientoBancario::whereRaw('1 = 0')->paginate($this->lineasXPagina);

        return view('livewire.movimientos-bancarios.lista', compact('items', 'cuentasBancarias'));
    }
}

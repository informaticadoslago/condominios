<?php

namespace App\Livewire\CuentasContables;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConBajaPorEstado;
use App\Livewire\Traits\ConFiltroEstado;
use App\Livewire\Traits\ConHistorialEstadoModal;
use App\Models\CuentaContable;
use App\Models\Estado;
use App\Models\TipoCuentaContable;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    use ConBajaPorEstado;
    use ConFiltroEstado;
    use ConHistorialEstadoModal;

    protected function modeloBaja(): string
    {
        return CuentaContable::class;
    }

    protected function modeloHistorial(): string
    {
        return CuentaContable::class;
    }

    public function mount()
    {
        $this->sort      = 'codigo';
        $this->direction = 'asc';
    }

    #[On('cuenta-contable-guardada')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    protected function columnasOrdenables(): ?array
    {
        return ['codigo', 'nombre'];
    }

    protected function modeloEstado(): string
    {
        return Estado::class;
    }

    protected function filtroTipo(): array
    {
        return [
            'clave' => 'tipo_cuenta_contable_id',
            'etiqueta' => __('Tipo'),
            'tipo' => 'select',
            'opciones' => [0 => __('Todos')] + $this->opcionesCacheadas(
                'tipo-cuenta-contable',
                fn () => TipoCuentaContable::orderBy('id')->pluck('descripcion', 'id')->all(),
            ),
            'neutro' => 0,
            'aplicar' => fn ($query, $valor) => $query->where('tipo_cuenta_contable_id', $valor),
        ];
    }

    public function definicionesFiltro(): array
    {
        return [
            $this->filtroTipo(),
            $this->filtroEstado(),
        ];
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = $this->aplicarFiltros(
            CuentaContable::with(['tipoCuentaContable', 'estado'])->withCount('historialEstados')
        )
            ->when($search, function ($q) use ($search) {
                $q->where('codigo', 'like', "%{$search}%")
                    ->orWhere('nombre', 'like', "%{$search}%");
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.cuentas-contables.lista', compact('items'));
    }
}

<?php

namespace App\Livewire\CuentasContables;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConArbolCuentasContables;
use App\Livewire\Traits\ConBajaPorEstado;
use App\Livewire\Traits\ConFiltroEstado;
use App\Livewire\Traits\ConHistorialEstadoModal;
use App\Models\CuentaContablePlantilla;
use App\Models\Estado;
use App\Models\TipoCuentaContable;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    use ConArbolCuentasContables;
    use ConBajaPorEstado;
    use ConFiltroEstado;
    use ConHistorialEstadoModal;

    protected function modeloBaja(): string
    {
        return CuentaContablePlantilla::class;
    }

    protected function modeloHistorial(): string
    {
        return CuentaContablePlantilla::class;
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

    protected function filtroPlantilla(): array
    {
        return [
            'clave' => 'plantilla',
            'etiqueta' => __('Plantilla'),
            'tipo' => 'select',
            'opciones' => [
                ''                                        => __('Todas'),
                'comun'                                    => __('Común'),
                CuentaContablePlantilla::PLANTILLA_COMUNIDAD => __('Comunidad'),
                CuentaContablePlantilla::PLANTILLA_SOCIEDAD  => __('Sociedad'),
            ],
            'neutro' => '',
            'aplicar' => fn ($query, $valor) => $valor === 'comun'
                ? $query->whereNull('plantilla')
                : $query->where('plantilla', $valor),
        ];
    }

    public function definicionesFiltro(): array
    {
        return [
            $this->filtroTipo(),
            $this->filtroPlantilla(),
            $this->filtroEstado(),
        ];
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $consultaBase = fn () => $this->aplicarFiltros(
            CuentaContablePlantilla::with(['tipoCuentaContable', 'estado'])
                ->withCount(['historialEstados', 'subcuentas'])
        )
            ->when($search, function ($q) use ($search) {
                $q->where('codigo', 'like', "%{$search}%")
                    ->orWhere('nombre', 'like', "%{$search}%");
            });

        $arbol = $this->modoArbol();

        // En árbol solo se paginan las raíces; sus descendientes cuelgan de ellas.
        $items = $consultaBase()
            ->when($arbol, fn ($q) => $q->whereNull('cuenta_padre_id'))
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        $filas = $arbol
            ? $this->filasArbol($items->getCollection(), $consultaBase)
            : $items->getCollection();

        return view('livewire.cuentas-contables.lista', compact('items', 'filas', 'arbol'));
    }
}

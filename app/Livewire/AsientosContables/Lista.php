<?php

namespace App\Livewire\AsientosContables;

use App\Livewire\ListaComponent;
use App\Models\AsientoContable;
use App\Models\EjercicioContable;

class Lista extends ListaComponent
{
    /** Índices de asientos con las líneas desplegadas en la tabla. */
    public array $expandido = [];

    public function mount()
    {
        $this->sort      = 'fecha';
        $this->direction = 'desc';
    }

    protected function columnasOrdenables(): ?array
    {
        return ['numero', 'fecha', 'concepto'];
    }

    public function toggleDetalle(int $id): void
    {
        if (in_array($id, $this->expandido, true)) {
            $this->expandido = array_values(array_diff($this->expandido, [$id]));
        } else {
            $this->expandido[] = $id;
        }
    }

    protected function filtroEjercicio(): array
    {
        return [
            'clave'    => 'ejercicio_contable_id',
            'etiqueta' => __('Ejercicio'),
            'tipo'     => 'select',
            'opciones' => [0 => __('Todos')] + EjercicioContable::where('comunidad_id', session('comunidad_actual_id'))
                ->orderByDesc('fecha_inicio')
                ->pluck('nombre', 'id')
                ->all(),
            'neutro'   => 0,
            'aplicar'  => fn ($query, $valor) => $query->where('ejercicio_contable_id', $valor),
        ];
    }

    public function definicionesFiltro(): array
    {
        return [
            $this->filtroEjercicio(),
        ];
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = $this->aplicarFiltros(
            AsientoContable::with(['ejercicioContable', 'apuntesContables.cuentaContable'])
                ->whereHas('ejercicioContable', fn ($q) => $q->where('comunidad_id', session('comunidad_actual_id')))
                ->withSum('apuntesContables as total_debe', 'debe')
        )
            ->when($search, function ($q) use ($search) {
                $q->where('concepto', 'like', "%{$search}%");
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.asientos-contables.lista', compact('items'));
    }
}

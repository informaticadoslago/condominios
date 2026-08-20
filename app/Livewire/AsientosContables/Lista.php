<?php

namespace App\Livewire\AsientosContables;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConEmpresaContableActiva;
use App\Models\AsientoContable;
use App\Models\EjercicioContable;

class Lista extends ListaComponent
{
    use ConEmpresaContableActiva;

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

    /** Lista contable: solo mostrar/ocultar, sin arrastrar (ver ListaComponent::permiteReordenarColumnas). */
    public function permiteReordenarColumnas(): bool
    {
        return false;
    }

    public function columnasDisponibles(): array
    {
        return [
            'numero'       => __('Número'),
            'fecha'        => __('Fecha'),
            'ejercicio'    => __('Ejercicio'),
            'concepto'     => __('Concepto'),
            'cuenta_debe'  => __('Debe'),
            'cuenta_haber' => __('Haber'),
            'importe'      => __('Importe'),
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
     * Defensa igual que sortValido() en ListaComponent: si el ejercicio guardado en
     * preferencias es de otra empresa contable (p.ej. tras cambiar de empresa activa
     * con este filtro puesto), se descarta en vez de dejar "+Nuevo" apuntando a un
     * ejercicio ajeno.
     */
    protected function cargarPreferencias(): void
    {
        parent::cargarPreferencias();

        $empresaContableId = $this->empresaContableActual()?->id ?? 0;
        $ejercicioId       = (int) ($this->filtros['ejercicio_contable_id'] ?? 0);

        if ($ejercicioId && ! EjercicioContable::where('id', $ejercicioId)->where('empresa_contable_id', $empresaContableId)->exists()) {
            $this->filtros['ejercicio_contable_id'] = 0;
            $this->guardarPreferencias();
        }
    }

    protected function filtroEjercicio(): array
    {
        $empresaContableId = $this->empresaContableActual()?->id ?? 0;

        return [
            'clave'    => 'ejercicio_contable_id',
            'etiqueta' => __('Ejercicio'),
            'tipo'     => 'select',
            'opciones' => [0 => __('Todos')] + EjercicioContable::where('empresa_contable_id', $empresaContableId)
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
        $empresaContableId = $this->empresaContableActual()?->id ?? 0;

        $items = $this->aplicarFiltros(
            AsientoContable::with(['ejercicioContable', 'apuntesContables.cuentaContable'])
                ->whereHas('ejercicioContable', fn ($q) => $q->where('empresa_contable_id', $empresaContableId))
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

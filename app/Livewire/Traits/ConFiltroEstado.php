<?php

namespace App\Livewire\Traits;

/**
 * Filtro de estado, común a todas las listas cuyo modelo tenga estado_id. La
 * lista declara el catálogo (modeloEstado) y añade filtroEstado() a sus
 * definicionesFiltro(). El valor 0 (Todos) no filtra.
 */
trait ConFiltroEstado
{
    /** Catálogo de estados de la lista, p.ej. EstadoAlumno::class. */
    abstract protected function modeloEstado(): string;

    protected function filtroEstado(string $columna = 'estado_id'): array
    {
        $modelo = $this->modeloEstado();

        return [
            'clave' => 'estado_id',
            'etiqueta' => __('Estado'),
            'tipo' => 'select',
            'opciones' => [0 => __('Todos')] + $this->opcionesCacheadas(
                'estado-'.$modelo,
                fn () => $modelo::orderBy('descripcion')->pluck('descripcion', 'id')->all(),
            ),
            'neutro' => 0,
            'aplicar' => fn ($query, $valor) => $query->where($columna, $valor),
        ];
    }
}

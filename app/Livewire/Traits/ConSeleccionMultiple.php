<?php

namespace App\Livewire\Traits;

/**
 * Selección de filas de una lista. No se persiste en disco ni BD: viaja en el propio
 * snapshot del componente Livewire, así que sobrevive a paginar, ordenar y cambiar de
 * filtro, pero se pierde si se sale de la pantalla o se refresca (F5).
 *
 * El checkbox de cada fila se enlaza con `wire:model.live="seleccionados"` (mismo
 * array, un `value` distinto por fila): así Livewire sincroniza también el estado
 * visual del checkbox en cada render (limpiar selección, por ejemplo), cosa que un
 * simple wire:click + @checked() no garantiza.
 *
 * "Ver solo seleccionados" ignora el resto de filtros (los declarados en
 * definicionesFiltro Y la búsqueda): la selección manda, aunque ya no case con ellos.
 */
trait ConSeleccionMultiple
{
    public array $seleccionados = [];

    public bool $verSoloSeleccionados = false;

    /** Ids de la página actualmente pintada, para el checkbox de cabecera. */
    public array $idsPaginaActual = [];

    /** ¿Están marcados TODOS los de la página actual? Lo enseña el checkbox de cabecera. */
    public bool $marcarTodosVisibles = false;

    public function limpiarSeleccion(): void
    {
        $this->seleccionados = [];
        $this->verSoloSeleccionados = false;
    }

    public function toggleVerSoloSeleccionados(): void
    {
        $this->verSoloSeleccionados = ! $this->verSoloSeleccionados;
        $this->resetPage();
    }

    /** Con "ver solo seleccionados" activo, la selección manda y el resto de filtros no se aplica. */
    protected function aplicarSeleccion($query, string $columnaId = 'id')
    {
        if ($this->verSoloSeleccionados) {
            return $query->whereIn($columnaId, $this->seleccionados ?: [0]);
        }

        return $this->aplicarFiltros($query);
    }

    /**
     * Llamar al final de render(), con la colección YA paginada: deja listas
     * $idsPaginaActual y $marcarTodosVisibles para el checkbox de cabecera.
     */
    protected function sincronizarSeleccionVisible($paginados): void
    {
        $this->idsPaginaActual = $paginados->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->marcarTodosVisibles = $this->idsPaginaActual !== []
            && ! array_diff($this->idsPaginaActual, $this->seleccionados);
    }

    /** El checkbox de cabecera: marca o desmarca solo los de la página visible. */
    public function updatedMarcarTodosVisibles($value): void
    {
        $this->seleccionados = $value
            ? array_values(array_unique(array_merge($this->seleccionados, $this->idsPaginaActual)))
            : array_values(array_diff($this->seleccionados, $this->idsPaginaActual));
    }

    /**
     * Invierte la selección dentro de $query (ya con sus filtros propios aplicados,
     * SIN los de esta selección): lo no marcado de ahí pasa a estarlo y viceversa. Lo
     * que estuviera marcado fuera de $query (p.ej. de un filtro anterior) no se toca.
     */
    protected function invertirSeleccionEn($query, string $columnaId = 'id'): void
    {
        $idsFiltrados = $this->aplicarFiltros($query)->pluck($columnaId)
            ->map(fn ($id) => (string) $id)->all();

        $fueraDelFiltro = array_diff($this->seleccionados, $idsFiltrados);
        $dentroInvertido = array_diff($idsFiltrados, $this->seleccionados);

        $this->seleccionados = array_values(array_merge($fueraDelFiltro, $dentroInvertido));
        $this->resetPage();
    }
}

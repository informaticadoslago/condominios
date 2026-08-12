<?php

namespace App\Livewire\Traits;

/**
 * Modal de historial de estados para una lista.
 *
 * El componente que lo use debe implementar:
 *   protected function modeloHistorial(): string   // FQCN del modelo (con trait ConHistorialEstado y relación estado())
 *
 * En la consulta de la lista añadir ->withCount('historialEstados') para poder
 * mostrar el botón solo cuando hay más de una línea de historial.
 */
trait ConHistorialEstadoModal
{
    public bool $historialAbierto = false;
    public array $historialLineas = [];
    public ?string $historialTitulo = null;

    abstract protected function modeloHistorial(): string;

    public function verHistorial($id): void
    {
        $clase    = $this->modeloHistorial();
        $registro = $clase::with('historialEstados')->find($id);

        if (! $registro) {
            return;
        }

        $catalogo = $registro->estado()->getRelated()->newQuery()->pluck('descripcion', 'id');

        $this->historialLineas = $registro->historialEstados
            ->sortByDesc('created_at')
            ->values()
            ->map(fn ($h, $i) => [
                'descripcion' => $catalogo[$h->estado_nuevo] ?? '—',
                'fecha'       => $h->fecha ? $h->fecha->format('d-m-Y') : optional($h->created_at)->format('d-m-Y H:i'),
                'actual'      => $i === 0,
            ])->all();

        $this->historialTitulo = $registro->nombreCompleto ?? $registro->name ?? null;
        $this->historialAbierto = true;
    }
}

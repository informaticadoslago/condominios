<?php

namespace App\Livewire\Traits;

use App\Models\EjercicioContable;

/**
 * Filtros «desde» y «hasta» de los informes contables.
 *
 * Todos van entre fechas, no por ejercicio: el ejercicio abierto es solo el rango con el
 * que arrancan de fábrica, y desde ahí se pide lo que se quiera —un mes, un trimestre o
 * un rango a caballo entre dos años.
 *
 * Lo que hace cada informe con las fechas no es lo mismo (el mayor filtra los apuntes,
 * el balance las usa para partir lo anterior de lo del periodo), así que el trait pone
 * el rango y los valores de fábrica, y cada lista declara sus filtros.
 *
 * Requiere ConEmpresaContableActiva.
 */
trait ConRangoContable
{
    /** El ejercicio con el que arranca el informe: el último abierto, o el más reciente. */
    protected function ejercicioDeArranque(): ?EjercicioContable
    {
        return EjercicioContable::where('empresa_contable_id', $this->empresaContableActual()?->id ?? 0)
            ->orderBy('cerrado')
            ->orderByDesc('fecha_inicio')
            ->first();
    }

    protected function filtrosPorDefecto(): array
    {
        $ejercicio = $this->ejercicioDeArranque();

        return array_merge(parent::filtrosPorDefecto(), [
            'desde' => $ejercicio?->fecha_inicio?->format('Y-m-d') ?? '',
            'hasta' => $ejercicio?->fecha_fin?->format('Y-m-d') ?? '',
        ]);
    }

    /** El rango puesto, ya normalizado: cadena vacía es «sin límite», o sea null. */
    protected function desde(): ?string
    {
        return ($this->filtros['desde'] ?? '') ?: null;
    }

    protected function hasta(): ?string
    {
        return ($this->filtros['hasta'] ?? '') ?: null;
    }
}

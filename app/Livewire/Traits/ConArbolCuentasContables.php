<?php

namespace App\Livewire\Traits;

/**
 * Pinta la lista de cuentas contables como un árbol plegado: de entrada solo se
 * ven las cuentas raíz, y cada una que tenga hijas se despliega con el chevron.
 * Las hijas que a su vez tengan hijas se despliegan otra vez, tantos niveles
 * como tenga el plan (hijas, nietas…).
 *
 * Con búsqueda o filtros puestos la lista se pinta plana, como antes: si se está
 * buscando una cuenta concreta no tiene sentido esconderla dentro de su rama.
 */
trait ConArbolCuentasContables
{
    /** ids de las cuentas que están desplegadas. */
    public array $expandido = [];

    public function alternarRama(int $id): void
    {
        if (in_array($id, $this->expandido, true)) {
            $this->expandido = array_values(array_diff($this->expandido, [$id]));
        } else {
            $this->expandido[] = $id;
        }
    }

    /** El árbol solo se pinta cuando no hay nada filtrando la lista. */
    protected function modoArbol(): bool
    {
        if (trim((string) $this->search) !== '') {
            return false;
        }

        foreach ($this->definicionesFiltro() as $filtro) {
            if (! $this->filtroNeutro($filtro, $this->filtros[$filtro['clave']] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Aplana el árbol para pintarlo como filas de una sola tabla: cada cuenta
     * lleva su profundidad en nivel_arbol y detrás van sus descendientes, en
     * orden, solo si está desplegada. Los hijos se leen por niveles (una
     * consulta por nivel desplegado, no una por cuenta).
     *
     * @param  \Closure  $consultaBase  devuelve la misma consulta que la lista, sin el filtro de raíces
     */
    protected function filasArbol($raices, \Closure $consultaBase)
    {
        $hijasPorPadre = [];
        $pendientes    = $this->idsExpandidos($raices);

        while ($pendientes) {
            $hijas = $consultaBase()
                ->whereIn('cuenta_padre_id', $pendientes)
                ->orderBy($this->sort, $this->direction)
                ->get();

            foreach ($hijas as $hija) {
                $hijasPorPadre[$hija->cuenta_padre_id][] = $hija;
            }

            $pendientes = $this->idsExpandidos($hijas);
        }

        $filas = collect();

        $añadir = function ($cuentas, int $nivel) use (&$añadir, &$filas, &$hijasPorPadre) {
            foreach ($cuentas as $cuenta) {
                $cuenta->nivel_arbol = $nivel;
                $filas->push($cuenta);

                if (in_array($cuenta->id, $this->expandido, true)) {
                    $añadir($hijasPorPadre[$cuenta->id] ?? [], $nivel + 1);
                }
            }
        };

        $añadir($raices, 0);

        return $filas;
    }

    /** De esas cuentas, las que están desplegadas: sus hijas hay que leerlas. */
    protected function idsExpandidos($cuentas): array
    {
        return collect($cuentas)
            ->pluck('id')
            ->filter(fn ($id) => in_array($id, $this->expandido, true))
            ->values()
            ->all();
    }
}

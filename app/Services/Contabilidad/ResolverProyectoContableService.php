<?php

namespace App\Services\Contabilidad;

use App\Models\ProyectoContable;
use Illuminate\Database\QueryException;

/**
 * Da de alta la dimensión analítica de una actividad —una torre, un negocio— dentro de
 * una empresa contable. No es una cuenta: no cuelga del plan, solo etiqueta apuntes.
 *
 * Igual que el resto de la frontera del módulo, `sujeto` es la etiqueta opaca de quien
 * llama: la contabilidad la guarda y la compara, pero no la interpreta. Pedir dos veces
 * lo mismo devuelve el mismo proyecto, no uno nuevo.
 */
final class ResolverProyectoContableService
{
    public function ejecutar(
        int $empresaContableId,
        string $nombre,
        string $sujetoTipo,
        string $sujetoId,
    ): ProyectoContable {
        if ($existente = $this->existente($empresaContableId, $sujetoTipo, $sujetoId)) {
            return $existente;
        }

        try {
            return ProyectoContable::create([
                'empresa_contable_id' => $empresaContableId,
                'nombre'              => $nombre,
                'sujeto_tipo'         => $sujetoTipo,
                'sujeto_id'           => $sujetoId,
            ]);
        } catch (QueryException $e) {
            // Dos peticiones idénticas a la vez: la que pierde choca contra el único del
            // sujeto. El proyecto existe, que es lo que quería quien llamó.
            if ($existente = $this->existente($empresaContableId, $sujetoTipo, $sujetoId)) {
                return $existente;
            }

            throw $e;
        }
    }

    private function existente(int $empresaContableId, string $sujetoTipo, string $sujetoId): ?ProyectoContable
    {
        return ProyectoContable::where('empresa_contable_id', $empresaContableId)
            ->where('sujeto_tipo', $sujetoTipo)
            ->where('sujeto_id', $sujetoId)
            ->first();
    }
}

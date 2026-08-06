<?php

namespace App\Services\Contabilidad;

use App\Exceptions\CuentaContableDesconocidaException;
use App\Models\CuentaContable;
use App\Models\TipoIngresoContable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Da la subcuenta de ingresos de algo que factura la comunidad: el presupuesto anual va
 * al grupo de cuotas y cada derrama tiene la suya dentro del grupo de derramas, para
 * poder verlas por separado en el mayor.
 *
 * No es un tercero: nadie debe nada aquí, es el concepto por el que se cobra. Lo único
 * que comparte con los terceros es cómo se numera (ver CrearSubcuentaService).
 *
 * Igual que el resto de la frontera del módulo, `sujeto` es la etiqueta opaca de quien
 * llama ('presupuesto:12'): la contabilidad la guarda y la compara, pero no la
 * interpreta ni sabe qué hay al otro lado. Pedir dos veces lo mismo devuelve la misma
 * cuenta, no una segunda.
 */
final class ResolverCuentaIngresoService
{
    public function __construct(private readonly CrearSubcuentaService $subcuentas)
    {
    }

    public function ejecutar(
        int $empresaContableId,
        string $clase,
        string $nombre,
        string $sujetoTipo,
        string $sujetoId,
    ): CuentaContable {
        if ($existente = $this->existente($empresaContableId, $sujetoTipo, $sujetoId)) {
            return $existente;
        }

        $tipo = TipoIngresoContable::where('codigo', $clase)->first();

        if (! $tipo) {
            throw new CuentaContableDesconocidaException("No existe la clase de ingreso «{$clase}».");
        }

        try {
            return DB::transaction(fn (): CuentaContable => $this->subcuentas->crear(
                empresaContableId: $empresaContableId,
                prefijo: $tipo->prefijo_cuenta,
                nombreGrupo: $tipo->descripcion,
                nombre: $nombre,
                sujetoTipo: $sujetoTipo,
                sujetoId: $sujetoId,
            ));
        } catch (QueryException $e) {
            // Dos peticiones idénticas a la vez: la que pierde choca contra el único del
            // sujeto. La cuenta existe, que es lo que quería quien llamó.
            if ($existente = $this->existente($empresaContableId, $sujetoTipo, $sujetoId)) {
                return $existente;
            }

            throw $e;
        }
    }

    private function existente(int $empresaContableId, string $sujetoTipo, string $sujetoId): ?CuentaContable
    {
        return CuentaContable::where('empresa_contable_id', $empresaContableId)
            ->where('sujeto_tipo', $sujetoTipo)
            ->where('sujeto_id', $sujetoId)
            ->first();
    }
}

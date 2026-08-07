<?php

namespace App\Services\Contabilidad;

use App\Models\CuentaContable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Da la subcuenta donde está el dinero: la de bancos (5720xxxx) de cada cuenta corriente.
 *
 * No es un tercero ni un ingreso —nadie debe nada aquí y no se factura nada—, es dónde
 * entra y sale el dinero. Con ellos solo comparte cómo se numera (ver CrearSubcuentaService).
 *
 * Igual que el resto de la frontera del módulo, `sujeto` es la etiqueta opaca de quien
 * llama ('cuenta_bancaria:7'): la contabilidad la guarda y la compara, pero no la
 * interpreta ni sabe qué hay al otro lado. Pedir dos veces lo mismo devuelve la misma
 * cuenta, no una segunda.
 */
final class ResolverCuentaTesoreriaService
{
    /** Grupo del que cuelga cada cuenta corriente. Tiene que existir en la empresa. */
    private const GRUPO_BANCOS = '5720';

    public function __construct(private readonly CrearSubcuentaService $subcuentas)
    {
    }

    public function banco(
        int $empresaContableId,
        string $nombre,
        string $sujetoTipo,
        string $sujetoId,
    ): CuentaContable {
        if ($existente = $this->existente($empresaContableId, $sujetoTipo, $sujetoId)) {
            return $existente;
        }

        try {
            return DB::transaction(fn (): CuentaContable => $this->subcuentas->crear(
                empresaContableId: $empresaContableId,
                prefijo: self::GRUPO_BANCOS,
                nombreGrupo: 'Bancos',
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

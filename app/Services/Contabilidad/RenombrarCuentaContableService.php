<?php

namespace App\Services\Contabilidad;

use App\Models\CuentaContable;

/**
 * Cambia la denominación de una cuenta, nada más.
 *
 * El código no se toca: una cuenta con movimientos tiene que seguir llamándose igual en
 * los listados que ya se entregaron. El nombre sí se puede cambiar —no forma parte de
 * ningún asiento, el mayor se vuelve a sacar con el de hoy y no altera ni un importe—,
 * pero es del contable, así que quien lo pida desde fuera debe haber preguntado antes.
 *
 * Devuelve false si esa empresa no tiene esa cuenta, que es lo que pasa cuando la
 * gestión guarda un código de una contabilidad que ya no está.
 */
final class RenombrarCuentaContableService
{
    public function ejecutar(int $empresaContableId, string $codigo, string $nombre): bool
    {
        $cuenta = CuentaContable::where('empresa_contable_id', $empresaContableId)
            ->where('codigo', $codigo)
            ->first();

        if (! $cuenta) {
            return false;
        }

        $cuenta->update(['nombre' => $nombre]);

        return true;
    }
}

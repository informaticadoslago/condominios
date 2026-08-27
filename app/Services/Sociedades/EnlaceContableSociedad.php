<?php

namespace App\Services\Sociedades;

use App\Models\CuentaBancaria;
use App\Models\Sociedad;
use App\Services\Contabilidad\ResolverCuentaTesoreriaService;
use Illuminate\Support\Facades\DB;

/**
 * Lo que la gestión le pide a la contabilidad cuando la sociedad está enlazada con una
 * empresa contable: la subcuenta de bancos de cada cuenta corriente. Igual que
 * EnlaceContableComunidad, pero sin propietarios ni presupuestos —eso no existe (todavía)
 * para una sociedad—, así que de momento solo cuentas bancarias.
 */
final class EnlaceContableSociedad
{
    public function __construct(
        private readonly ResolverCuentaTesoreriaService $tesoreria,
    ) {
    }

    /**
     * Da de alta la cuenta corriente de la sociedad como subcuenta de bancos (57200001)
     * y se la guarda. Hace falta el nombre contable, que es el que se lee en el mayor.
     * Devuelve null también si la cuenta no es de una sociedad o esta no lleva
     * contabilidad.
     */
    public function asignarCuentaBancaria(CuentaBancaria $cuenta): ?string
    {
        if ($cuenta->cuenta_contable) {
            return $cuenta->cuenta_contable;
        }

        $sociedad  = $cuenta->titular instanceof Sociedad ? $cuenta->titular : null;
        $empresaId = $sociedad?->empresa_contable_id;

        if (! $empresaId || ! $cuenta->nombre_contable) {
            return null;
        }

        $cuentaContable = DB::transaction(fn () => $this->tesoreria->banco(
            empresaContableId: $empresaId,
            nombre: $cuenta->nombre_contable,
            sujetoTipo: 'cuenta_bancaria',
            sujetoId: (string) $cuenta->id,
        ));

        $cuenta->update(['cuenta_contable' => $cuentaContable->codigo]);

        return $cuentaContable->codigo;
    }
}

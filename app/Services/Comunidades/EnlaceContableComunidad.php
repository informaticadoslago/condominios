<?php

namespace App\Services\Comunidades;

use App\Models\Comunidad;
use App\Models\CuentaBancaria;
use App\Models\Presupuesto;
use App\Models\Propietario;
use App\Services\Contabilidad\DatosTercero;
use App\Services\Contabilidad\ResolvedorCuentasService;
use App\Services\Contabilidad\ResolverCuentaIngresoService;
use App\Services\Contabilidad\ResolverCuentaTesoreriaService;
use App\Services\Contabilidad\RenombrarCuentaContableService;
use Illuminate\Support\Facades\DB;

/**
 * Lo que la gestión le pide a la contabilidad cuando la comunidad está enlazada con una
 * empresa contable: la subcuenta de cliente de cada propietario, la de ingresos de cada
 * presupuesto y la de bancos de cada cuenta corriente. Si la comunidad no está enlazada
 * no se hace nada, que es el caso de las comunidades que solo se administran.
 *
 * La llamada es directa al servicio, no por HTTP: la API es la puerta de los sistemas
 * que viven fuera de esta aplicación. Desde dentro se llama al servicio para poder
 * compartir la transacción con lo que haya originado la petición.
 *
 * Las cuentas se guardan como texto en gestión —nunca un id de las tablas contables—,
 * porque la contabilidad no conoce a la gestión y la flecha va en un solo sentido.
 */
final class EnlaceContableComunidad
{
    public function __construct(
        private readonly ResolvedorCuentasService $terceros,
        private readonly ResolverCuentaIngresoService $ingresos,
        private readonly ResolverCuentaTesoreriaService $tesoreria,
        private readonly RenombrarCuentaContableService $renombrar,
    ) {
    }

    /**
     * Da de alta al propietario como cliente y le guarda su subcuenta (43000001).
     * Devuelve null si la comunidad no lleva contabilidad. Volver a llamar no crea otra:
     * la contabilidad reconoce al mismo sujeto y devuelve la que ya tenía.
     */
    public function asignarCuentaPropietario(Propietario $propietario): ?string
    {
        if ($propietario->cuenta_contable) {
            return $propietario->cuenta_contable;
        }

        $persona   = $propietario->persona;
        $empresaId = $persona?->comunidad?->empresa_contable_id;

        if (! $empresaId) {
            return null;
        }

        $cuenta = DB::transaction(fn () => $this->terceros->resolver(
            $empresaId,
            new DatosTercero(
                tipo: 'propietario',
                id: (string) $propietario->id,
                clase: 'cliente',
                nif: $persona->documento_identificativo,
                razonSocial: $persona->nombreCompleto,
            ),
            puedeCrear: true,
        ));

        $propietario->update(['cuenta_contable' => $cuenta->codigo]);

        return $cuenta->codigo;
    }

    /**
     * Da de alta de golpe a los propietarios que se le digan y devuelve cuántos han
     * quedado con cuenta. Los que ya la tenían no cuentan: no se les toca nada.
     *
     * @param  array<int|string>  $propietarioIds
     * @return array{enlazados: int, omitidos: int}
     */
    public function asignarCuentasPropietarios(array $propietarioIds): array
    {
        $propietarios = Propietario::with('persona')
            ->whereIn('id', $propietarioIds)
            ->whereNull('cuenta_contable')
            ->get();

        $enlazados = 0;

        foreach ($propietarios as $propietario) {
            if ($this->asignarCuentaPropietario($propietario)) {
                $enlazados++;
            }
        }

        return [
            'enlazados' => $enlazados,
            'omitidos'  => $propietarios->count() - $enlazados,
        ];
    }

    /**
     * Da de alta la cuenta de ingresos del presupuesto y se la guarda: 75000001 si es de
     * cuotas, 75010001 si es una derrama. Cada derrama tiene la suya para poder verla
     * por separado en el mayor.
     */
    public function asignarCuentaIngresoPresupuesto(Presupuesto $presupuesto): ?string
    {
        if ($presupuesto->cuenta_contable) {
            return $presupuesto->cuenta_contable;
        }

        $empresaId = $presupuesto->comunidad?->empresa_contable_id;

        if (! $empresaId) {
            return null;
        }

        $cuenta = $this->ingresos->ejecutar(
            empresaContableId: $empresaId,
            clase: $presupuesto->tipoPresupuesto->codigo_ingreso,
            nombre: $presupuesto->nombre,
            sujetoTipo: 'presupuesto',
            sujetoId: (string) $presupuesto->id,
        );

        $presupuesto->update(['cuenta_contable' => $cuenta->codigo]);

        return $cuenta->codigo;
    }

    /**
     * Da de alta la cuenta corriente de la comunidad como subcuenta de bancos (57200001)
     * y se la guarda: es donde entra el dinero de los recibos cobrados.
     *
     * Hace falta el nombre contable, que es el que se lee en el mayor y lo escribe quien
     * lleva la comunidad. Sin él no se estrena nada: una cuenta llamada «cuenta_bancaria
     * 7» no le dice nada a nadie. Devuelve null también si la cuenta no es de una
     * comunidad —la de un propietario o un proveedor no es tesorería nuestra— o si esa
     * comunidad no lleva contabilidad.
     */
    public function asignarCuentaBancaria(CuentaBancaria $cuenta): ?string
    {
        if ($cuenta->cuenta_contable) {
            return $cuenta->cuenta_contable;
        }

        $comunidad = $cuenta->titular instanceof Comunidad ? $cuenta->titular : null;
        $empresaId = $comunidad?->empresa_contable_id;

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

    /**
     * Pone en la contabilidad el nombre contable que tiene ahora la cuenta bancaria.
     *
     * Solo se llama cuando el usuario ha dicho que sí: en el plan manda el contable, y
     * puede haber corregido allí la denominación a propósito.
     */
    public function renombrarCuentaBancaria(CuentaBancaria $cuenta): bool
    {
        $comunidad = $cuenta->titular instanceof Comunidad ? $cuenta->titular : null;
        $empresaId = $comunidad?->empresa_contable_id;

        if (! $empresaId || ! $cuenta->cuenta_contable || ! $cuenta->nombre_contable) {
            return false;
        }

        return $this->renombrar->ejecutar($empresaId, $cuenta->cuenta_contable, $cuenta->nombre_contable);
    }
}

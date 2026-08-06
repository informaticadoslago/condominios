<?php

namespace App\Services\Contabilidad;

use App\Exceptions\TerceroContableDesconocidoException;
use App\Models\CuentaContable;
use App\Models\TerceroContable;
use App\Models\TipoTerceroContable;

/**
 * Traduce un tercero a su subcuenta.
 *
 * Cómo se construye el código lo sabe CrearSubcuentaService, que es el mismo que numera
 * las cuentas de ingreso. Ni la gestión, ni el registro de asientos, ni quien entre por
 * la API conocen esa regla.
 */
final class ResolvedorCuentasService
{
    public function __construct(private readonly CrearSubcuentaService $subcuentas)
    {
    }

    /**
     * Debe ejecutarse dentro de una transacción cuando $puedeCrear es true: el bloqueo
     * de la cuenta de grupo solo sirve mientras dure.
     */
    public function resolver(int $empresaContableId, DatosTercero $tercero, bool $puedeCrear = false): CuentaContable
    {
        $existente = TerceroContable::where('empresa_contable_id', $empresaContableId)
            ->where('sujeto_tipo', $tercero->tipo)
            ->where('sujeto_id', $tercero->id)
            ->first();

        if ($existente) {
            return $existente->cuentaContable;
        }

        if (! $puedeCrear) {
            throw new TerceroContableDesconocidoException(
                "El tercero «{$tercero->tipo}:{$tercero->id}» no tiene subcuenta en esa empresa contable. "
                .'Dalo de alta antes, o manda «crear_terceros_desconocidos» si es una carga inicial.'
            );
        }

        return $this->crear($empresaContableId, $tercero)->cuentaContable;
    }

    private function crear(int $empresaContableId, DatosTercero $tercero): TerceroContable
    {
        if ($tercero->clase === null) {
            throw new TerceroContableDesconocidoException(
                "Para crear el tercero «{$tercero->tipo}:{$tercero->id}» hace falta su clase (cliente, proveedor…)."
            );
        }

        $tipo = TipoTerceroContable::where('codigo', $tercero->clase)->first();

        if (! $tipo) {
            throw new TerceroContableDesconocidoException("No existe la clase de tercero «{$tercero->clase}».");
        }

        // La subcuenta del tercero no lleva la etiqueta opaca: de eso ya se encarga el
        // propio tercero, y tenerla en dos sitios sería tener dos verdades.
        $cuenta = $this->subcuentas->crear(
            empresaContableId: $empresaContableId,
            prefijo: $tipo->prefijo_cuenta,
            nombreGrupo: $tipo->descripcion,
            nombre: $tercero->razonSocial ?? "{$tercero->tipo} {$tercero->id}",
        );

        return TerceroContable::create([
            'empresa_contable_id'      => $empresaContableId,
            'tipo_tercero_contable_id' => $tipo->id,
            'sujeto_tipo'              => $tercero->tipo,
            'sujeto_id'                => $tercero->id,
            'nif'                      => $tercero->nif,
            'razon_social'             => $tercero->razonSocial ?? "{$tercero->tipo} {$tercero->id}",
            'cuenta_contable_id'       => $cuenta->id,
            'estado_id'                => TerceroContable::ESTADO_ACTIVO,
        ]);
    }
}

<?php

namespace App\Services\Contabilidad;

use App\Exceptions\CuentaContableDesconocidaException;
use App\Exceptions\SubcuentasAgotadasException;
use App\Exceptions\TerceroContableDesconocidoException;
use App\Models\CuentaContable;
use App\Models\TerceroContable;
use App\Models\TipoTerceroContable;

/**
 * Traduce un tercero a su subcuenta.
 *
 * Es el único sitio de todo el sistema que sabe cómo se construye un código de subcuenta
 * (4 dígitos de grupo + 4 de correlativo). Ni la gestión, ni el registro de asientos, ni
 * quien entre por la API conocen esa regla.
 */
final class ResolvedorCuentasService
{
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

        // Se bloquea la cuenta de grupo, no la del tercero: la del tercero todavía no
        // existe y no hay nada que bloquear. El grupo sí existe siempre, y es lo que
        // serializa a dos altas simultáneas que competirían por el mismo correlativo.
        $grupo = CuentaContable::where('empresa_contable_id', $empresaContableId)
            ->where('codigo', $tipo->codigoCuentaGrupo())
            ->lockForUpdate()
            ->first();

        if (! $grupo) {
            throw new CuentaContableDesconocidaException(
                "La empresa contable no tiene la cuenta de grupo {$tipo->codigoCuentaGrupo()} ({$tipo->descripcion})."
            );
        }

        $cuenta = CuentaContable::create([
            'empresa_contable_id'     => $empresaContableId,
            'tipo_cuenta_contable_id' => $grupo->tipo_cuenta_contable_id,
            'cuenta_padre_id'         => $grupo->id,
            'codigo'                  => $this->siguienteCodigo($empresaContableId, $tipo),
            'nombre'                  => $tercero->razonSocial ?? "{$tercero->tipo} {$tercero->id}",
            'estado_id'               => CuentaContable::ESTADO_ACTIVO,
        ]);

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

    private function siguienteCodigo(int $empresaContableId, TipoTerceroContable $tipo): string
    {
        $ultimo = CuentaContable::where('empresa_contable_id', $empresaContableId)
            ->where('codigo', 'like', $tipo->prefijo_cuenta.'%')
            ->where('codigo', '!=', $tipo->codigoCuentaGrupo())
            ->max('codigo');

        $siguiente = $ultimo === null ? 1 : ((int) substr((string) $ultimo, 4)) + 1;

        // Decisión tomada: al agotar el grupo se para y se avisa, no se salta al grupo
        // siguiente. Así un grupo sigue siendo un prefijo único en informes y exportaciones.
        if ($siguiente > 9999) {
            throw new SubcuentasAgotadasException(
                "Agotadas las 9.999 subcuentas del grupo {$tipo->prefijo_cuenta} ({$tipo->descripcion})."
            );
        }

        return $tipo->prefijo_cuenta.str_pad((string) $siguiente, 4, '0', STR_PAD_LEFT);
    }
}

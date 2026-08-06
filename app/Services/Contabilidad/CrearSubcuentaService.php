<?php

namespace App\Services\Contabilidad;

use App\Exceptions\CuentaContableDesconocidaException;
use App\Exceptions\SubcuentasAgotadasException;
use App\Models\CuentaContable;

/**
 * Cuelga una subcuenta nueva del grupo que se le diga.
 *
 * Es el único sitio de todo el sistema que sabe cómo se construye un código de subcuenta
 * (4 dígitos de grupo + 4 de correlativo). Lo usan por igual el alta de terceros —el
 * propietario que se hace cliente— y el alta de cuentas de ingreso —el presupuesto o la
 * derrama—, que numeran igual aunque no tengan nada más en común.
 */
final class CrearSubcuentaService
{
    /**
     * Debe ejecutarse dentro de una transacción: el bloqueo de la cuenta de grupo, que
     * es lo que impide que dos altas simultáneas se lleven el mismo correlativo, solo
     * sirve mientras dure.
     *
     * $sujetoTipo y $sujetoId son la etiqueta opaca de quien pide la subcuenta, y solo
     * la llevan las que no cuelgan de un tercero (ahí la etiqueta vive en el tercero).
     */
    public function crear(
        int $empresaContableId,
        string $prefijo,
        string $nombreGrupo,
        string $nombre,
        ?string $sujetoTipo = null,
        ?string $sujetoId = null,
    ): CuentaContable {
        // Se bloquea la cuenta de grupo, no la nueva: la nueva todavía no existe y no hay
        // nada que bloquear. El grupo sí existe siempre, y es lo que serializa a dos
        // altas simultáneas que competirían por el mismo correlativo.
        $grupo = CuentaContable::where('empresa_contable_id', $empresaContableId)
            ->where('codigo', $prefijo.'0000')
            ->lockForUpdate()
            ->first();

        if (! $grupo) {
            throw new CuentaContableDesconocidaException(
                "La empresa contable no tiene la cuenta de grupo {$prefijo}0000 ({$nombreGrupo})."
            );
        }

        return CuentaContable::create([
            'empresa_contable_id'     => $empresaContableId,
            'tipo_cuenta_contable_id' => $grupo->tipo_cuenta_contable_id,
            'cuenta_padre_id'         => $grupo->id,
            'codigo'                  => $this->siguienteCodigo($empresaContableId, $prefijo, $nombreGrupo),
            'nombre'                  => $nombre,
            'sujeto_tipo'             => $sujetoTipo,
            'sujeto_id'               => $sujetoId,
            'estado_id'               => CuentaContable::ESTADO_ACTIVO,
        ]);
    }

    private function siguienteCodigo(int $empresaContableId, string $prefijo, string $nombreGrupo): string
    {
        $ultimo = CuentaContable::where('empresa_contable_id', $empresaContableId)
            ->where('codigo', 'like', $prefijo.'%')
            ->where('codigo', '!=', $prefijo.'0000')
            ->max('codigo');

        $siguiente = $ultimo === null ? 1 : ((int) substr((string) $ultimo, 4)) + 1;

        // Decisión tomada: al agotar el grupo se para y se avisa, no se salta al grupo
        // siguiente. Así un grupo sigue siendo un prefijo único en informes y exportaciones.
        if ($siguiente > 9999) {
            throw new SubcuentasAgotadasException(
                "Agotadas las 9.999 subcuentas del grupo {$prefijo} ({$nombreGrupo})."
            );
        }

        return $prefijo.str_pad((string) $siguiente, 4, '0', STR_PAD_LEFT);
    }
}

<?php

namespace App\Models;

use App\Models\Traits\ConHistorialEstado;
use Illuminate\Database\Eloquent\Model;

class CuentaContable extends Model
{
    use ConHistorialEstado;

    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA = 2;

    protected $table = 'cuenta_contables';

    protected $fillable = [
        'empresa_contable_id', 'tipo_cuenta_contable_id', 'cuenta_padre_id', 'codigo', 'nombre', 'estado_id',
    ];

    public function empresaContable()
    {
        return $this->belongsTo(EmpresaContable::class);
    }

    public function tipoCuentaContable()
    {
        return $this->belongsTo(TipoCuentaContable::class);
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    public function cuentaPadre()
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_padre_id');
    }

    public function subcuentas()
    {
        return $this->hasMany(CuentaContable::class, 'cuenta_padre_id');
    }

    /**
     * Copia (no enlaza) el plan de cuentas global a una empresa contable recién
     * creada: si el plan global cambia después, las cuentas ya copiadas no se
     * ven afectadas. La jerarquía se recalcula por código (grupo = 4 dígitos +
     * "0000"), igual que hacen CuentasContables\Formulario y PlanDeCuentas\Formulario.
     */
    public static function copiarPlanGlobalA(EmpresaContable $empresaContable): void
    {
        $nuevoIdPorCodigo = [];

        foreach (self::whereNull('empresa_contable_id')->orderBy('codigo')->get() as $global) {
            $codigoGrupo = substr($global->codigo, 0, 4).'0000';
            $padreId     = $codigoGrupo !== $global->codigo ? ($nuevoIdPorCodigo[$codigoGrupo] ?? null) : null;

            $nueva = self::create([
                'empresa_contable_id'     => $empresaContable->id,
                'tipo_cuenta_contable_id' => $global->tipo_cuenta_contable_id,
                'cuenta_padre_id'         => $padreId,
                'codigo'                  => $global->codigo,
                'nombre'                  => $global->nombre,
                'estado_id'               => $global->estado_id,
            ]);

            $nuevoIdPorCodigo[$global->codigo] = $nueva->id;
        }
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una fila por empresa contable y por clase de cargo bancario (remesa/mantenimiento),
 * con su cuenta de gasto ya resuelta. No es un catálogo global de dos filas fijas: cada
 * empresa tiene la suya, porque cada una tiene su propio plan de cuentas.
 */
class TipoComisionBancaria extends Model
{
    const
        REMESA = 'remesa',
        MANTENIMIENTO = 'mantenimiento';

    protected $table = 'tipo_comisiones_bancarias';

    protected $fillable = ['empresa_contable_id', 'codigo', 'descripcion', 'cuenta_contable_id'];

    public function empresaContable()
    {
        return $this->belongsTo(EmpresaContable::class);
    }

    public function cuentaContable()
    {
        return $this->belongsTo(CuentaContable::class);
    }

    public function comisionesBancarias()
    {
        return $this->hasMany(ComisionBancaria::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCuentaContable extends Model
{
    protected $table = 'tipo_cuenta_contables';

    const
    ACTIVO = 1,
    PASIVO = 2,
    PATRIMONIO_NETO = 3,
    INGRESO = 4,
    GASTO = 5;

    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA = 2;

    protected $fillable = ['descripcion', 'estado_id'];

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }

    public function scopeActivo($query)
    {
        return $query->where('estado_id', self::ESTADO_ACTIVO);
    }
}

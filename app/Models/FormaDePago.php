<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormaDePago extends Model
{
    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA = 2;

    const
    RECIBO_BANCARIO = 1,
    EFECTIVO = 2,
    TRANSFERENCIA = 3,
    // Salda un recibo con el saldo a favor que el propietario ya tenía, sin dinero
    // nuevo por banco (ver EnlazarCobrosContabilidad::enlazarCompensacion).
    COMPENSACION = 4;

    protected $table = 'formas_de_pago';

    public $timestamps = false;

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

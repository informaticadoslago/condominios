<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormaDePago extends Model
{
    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA = 2;

    // Ids conservados de tipos_de_tipos (grupo 152).
    const
    RECIBO_BANCARIO = 153,
    EFECTIVO = 154;

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

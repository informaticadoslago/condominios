<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoPeriodicidadPago extends Model
{
    protected $table = 'tipo_periodicidad_pagos';

    const
    MENSUAL = 1,
    BIMESTRAL = 2,
    TRIMESTRAL = 3,
    SEMESTRAL = 4,
    ANUAL = 5;

    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA = 2;

    protected $fillable = ['descripcion', 'meses', 'estado_id'];

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }

    public function scopeActivo($query)
    {
        return $query->where('estado_id', self::ESTADO_ACTIVO);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpresaAcreedor extends Model
{
    protected $table = 'empresa_acreedores';

    // Estados heredados de L9 (K_CUENTAACREEDORES_ESTADO)
    const
    ESTADO_ACTIVA = 1,
    ESTADO_BAJA = 2;

    protected $fillable = [
        'empresa_id',
        'nombrecuenta',
        'nombreacreedor',
        'ibanacreedor',
        'bicacreedor',
        'moneda',
        'idsimple',
        'idcompleto',
        'iso',
        'tipo',
        'plazo',
        'mindiasejecucion',
        'estado',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function scopeActiva($query)
    {
        return $query->where('estado', self::ESTADO_ACTIVA);
    }
}

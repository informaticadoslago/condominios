<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Cargo bancario que se registra a mano y aparte del circuito de recibos: comisión de liquidar una remesa, o mantenimiento y administración de cuenta. */
class ComisionBancaria extends Model
{
    protected $table = 'comisiones_bancarias';

    protected $fillable = [
        'cuenta_bancaria_id',
        'remesa_id',
        'tipo_comision_bancaria_id',
        'fecha',
        'concepto',
        'referencia',
        'asiento_contable',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function cuentaBancaria()
    {
        return $this->belongsTo(CuentaBancaria::class);
    }

    public function remesa()
    {
        return $this->belongsTo(Remesa::class);
    }

    public function tipoComisionBancaria()
    {
        return $this->belongsTo(TipoComisionBancaria::class);
    }

    public function lineas()
    {
        return $this->hasMany(LineaComisionBancaria::class);
    }
}

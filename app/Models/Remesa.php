<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Envío de adeudos al banco: agrupa los recibos domiciliados de un vencimiento. */
class Remesa extends Model
{
    protected $table = 'remesas';

    protected $fillable = [
        'comunidad_id',
        'cuenta_bancaria_id',
        'referencia',
        'fecha_cargo',
    ];

    protected $casts = [
        'fecha_cargo' => 'date',
    ];

    public function comunidad()
    {
        return $this->belongsTo(Comunidad::class);
    }

    public function cuentaBancaria()
    {
        return $this->belongsTo(CuentaBancaria::class);
    }

    public function lineas()
    {
        return $this->hasMany(LineaRemesa::class);
    }

    public function recibos()
    {
        return $this->belongsToMany(Recibo::class, 'lineas_remesas');
    }

    /** Comisiones bancarias asociadas: liquidación de la remesa o devolución de sus recibos. */
    public function comisionesBancarias()
    {
        return $this->hasMany(ComisionBancaria::class);
    }

    /** Sus líneas de comisión (comisión + IVA), para sumarlas con withSum sin N+1. */
    public function lineasComisionesBancarias()
    {
        return $this->hasManyThrough(
            LineaComisionBancaria::class,
            ComisionBancaria::class,
            'remesa_id',
            'comision_bancaria_id',
        );
    }
}

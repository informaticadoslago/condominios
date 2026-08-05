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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un recibo dentro de una remesa: el intento de cobro por domiciliación.
 *
 * Un recibo devuelto y vuelto a presentar tiene una línea por intento, cada una con su
 * propia devolución o su propio cobro. El importe es siempre el total del recibo: una
 * devolución SEPA no puede ser parcial.
 */
class LineaRemesa extends Model
{
    protected $table = 'lineas_remesas';

    protected $fillable = [
        'remesa_id',
        'recibo_id',
        'importe',
        'iban',
        'fecha_devolucion',
        'motivo_devolucion',
    ];

    protected $casts = [
        'importe'          => 'decimal:2',
        'fecha_devolucion' => 'date',
    ];

    public function remesa()
    {
        return $this->belongsTo(Remesa::class);
    }

    public function recibo()
    {
        return $this->belongsTo(Recibo::class);
    }

    public function cobros()
    {
        return $this->hasMany(Cobro::class);
    }

    public function scopeDevuelta($query)
    {
        return $query->whereNotNull('fecha_devolucion');
    }

    public function estaDevuelta(): bool
    {
        return $this->fecha_devolucion !== null;
    }
}

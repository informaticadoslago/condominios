<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Titularidad de un inmueble: qué % tiene cada propietario, y desde/hasta cuándo.
 * Es historia inmutable: un cambio de titular (venta, herencia, divorcio…) NUNCA
 * modifica ni borra una fila existente — cierra la vigente (fecha_fin) y abre una
 * nueva. Solo se corrige (UPDATE) una fila cuando fue un error de captura, nunca
 * para reflejar un cambio real de propiedad.
 */
class Titularidad extends Model
{
    protected $table = 'titularidades';

    const
    CAUSA_COMPRAVENTA = 'compraventa',
    CAUSA_HERENCIA = 'herencia',
    CAUSA_DONACION = 'donacion',
    CAUSA_DIVORCIO = 'divorcio';

    protected $fillable = [
        'inmueble_id',
        'propietario_id',
        'cuota_percent',
        'causa',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'cuota_percent' => 'decimal:2',
        'fecha_inicio'  => 'date',
        'fecha_fin'     => 'date',
    ];

    public function inmueble()
    {
        return $this->belongsTo(Inmueble::class);
    }

    public function propietario()
    {
        return $this->belongsTo(Propietario::class);
    }

    public function scopeVigente($query)
    {
        return $query->whereNull('fecha_fin');
    }
}

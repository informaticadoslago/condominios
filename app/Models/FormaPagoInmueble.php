<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Forma de pago vigente de un inmueble (transferencia, recibo bancario…) y, si es
 * recibo bancario, la cuenta bancaria concreta usada. Es historia inmutable, igual
 * que Titularidad: un cambio de cuenta o de forma de pago NUNCA modifica la fila
 * vigente — la cierra (fecha_fin) y abre una nueva.
 */
class FormaPagoInmueble extends Model
{
    protected $table = 'formas_pago_inmuebles';

    protected $fillable = [
        'inmueble_id',
        'forma_de_pago_id',
        'cuenta_bancaria_id',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];

    public function inmueble()
    {
        return $this->belongsTo(Inmueble::class);
    }

    public function formaDePago()
    {
        return $this->belongsTo(FormaDePago::class);
    }

    public function cuentaBancaria()
    {
        return $this->belongsTo(CuentaBancaria::class);
    }

    public function scopeVigente($query)
    {
        return $query->whereNull('fecha_fin');
    }
}

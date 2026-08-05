<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un movimiento de dinero sobre un recibo, con signo: positivo cobra, negativo devuelve.
 *
 * Es la entidad única de todo cobro venga del canal que venga (remesa, transferencia,
 * efectivo), y por eso es lo que la contabilidad referencia para su asiento. Referenciar
 * el recibo no valdría: un recibo cobrado en dos veces, o devuelto y vuelto a presentar,
 * repetiría la terna (tipo, id, evento) y RegistrarAsientoService se comería el segundo
 * asiento tomándolo por un reenvío del primero.
 *
 * Nunca se edita ni se borra una fila para corregir un cobro: se añade la contraria.
 */
class Cobro extends Model
{
    protected $table = 'cobros';

    protected $fillable = [
        'recibo_id',
        'forma_de_pago_id',
        'linea_remesa_id',
        'fecha',
        'importe',
    ];

    protected $casts = [
        'fecha'   => 'date',
        'importe' => 'decimal:2',
    ];

    public function recibo()
    {
        return $this->belongsTo(Recibo::class);
    }

    public function formaDePago()
    {
        return $this->belongsTo(FormaDePago::class);
    }

    public function lineaRemesa()
    {
        return $this->belongsTo(LineaRemesa::class);
    }

    public function esDevolucion(): bool
    {
        return (float) $this->importe < 0;
    }
}

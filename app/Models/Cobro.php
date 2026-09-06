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
 *
 * Puede no tener recibo: un pago puede sumar más de lo que cubre, y ese sobrante no es de
 * ningún recibo —un recibo no se paga por más de lo que vale, ver RegistrarCobro::
 * registrarPago—, así que va suelto, con `propietario_id` en vez de `recibo_id`.
 */
class Cobro extends Model
{
    protected $table = 'cobros';

    protected $fillable = [
        'recibo_id',
        // Solo con recibo_id null: a quién abonar el sobrante.
        'propietario_id',
        'forma_de_pago_id',
        'linea_remesa_id',
        'fecha',
        'importe',
        // Solo se teclea con Compensación; el resto de formas de pago ya se explican
        // solas. Es lo que EnlazarCobrosContabilidad pone en la contrapartida del asiento.
        'concepto',
        // Asiento en el que entró; lo pone EnlazarCobrosContabilidad.
        'asiento_contable',
    ];

    protected $casts = [
        'fecha'   => 'date',
        'importe' => 'decimal:2',
    ];

    public function recibo()
    {
        return $this->belongsTo(Recibo::class);
    }

    /** Solo presente en el sobrante; con recibo, la cuenta se llega a través de él. */
    public function propietario()
    {
        return $this->belongsTo(Propietario::class);
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

<?php

namespace App\Models;

use App\Models\Traits\ConHistorialEstado;
use Illuminate\Database\Eloquent\Model;

/**
 * Un vencimiento concreto de un inmueble dentro de un presupuesto aprobado.
 *
 * Se vuelca a disco al aprobar el presupuesto y ya no se recalcula: hasta entonces el
 * reparto se ve al vuelo y puede cambiar (los conceptos se editan, y la rotación de
 * céntimos avanza al aprobar), así que sin este volcado el reparto aprobado en junta
 * dejaría de ser el que enseña el sistema.
 *
 * Lleva copiada la forma de pago del inmueble para poder responder «quién debe qué y
 * cómo lo paga» sin pisar la contabilidad, y para armar la remesa por fecha de
 * vencimiento.
 */
class Recibo extends Model
{
    use ConHistorialEstado;

    protected $table = 'recibos';

    protected $fillable = [
        'presupuesto_id',
        'inmueble_id',
        'propietario_id',
        'numero_pago',
        'fecha_vencimiento',
        'importe',
        'importe_pagado',
        // Comisiones de devolución que se le repercuten; el saldo las suma al importe.
        'gastos_devolucion',
        'forma_de_pago_id',
        'cuenta_bancaria_id',
        'estado_id',
        // Asiento en el que entró; lo pone EnlazarRecibosContabilidad.
        'asiento_contable',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'importe'           => 'decimal:2',
        'importe_pagado'    => 'decimal:2',
        'gastos_devolucion' => 'decimal:2',
        'saldo'             => 'decimal:2',
    ];

    public function presupuesto()
    {
        return $this->belongsTo(Presupuesto::class);
    }

    public function inmueble()
    {
        return $this->belongsTo(Inmueble::class);
    }

    public function propietario()
    {
        return $this->belongsTo(Propietario::class);
    }

    public function formaDePago()
    {
        return $this->belongsTo(FormaDePago::class);
    }

    public function cuentaBancaria()
    {
        return $this->belongsTo(CuentaBancaria::class);
    }

    public function estado()
    {
        return $this->belongsTo(TipoEstadoRecibo::class, 'estado_id');
    }

    public function cobros()
    {
        return $this->hasMany(Cobro::class);
    }

    public function lineasRemesas()
    {
        return $this->hasMany(LineaRemesa::class);
    }

    /** Avisos mandados por este recibo, del más reciente al más antiguo. */
    public function avisos()
    {
        return $this->hasMany(CorreoEnviado::class)->orderByDesc('enviado_at');
    }

    /** Pendiente de cobro: `saldo` es columna generada, la mantiene el motor. */
    public function scopePendiente($query)
    {
        return $query->where('saldo', '>', 0);
    }

    public function scopeVencidoHasta($query, $fecha)
    {
        return $query->where('fecha_vencimiento', '<=', $fecha);
    }

    /** Se cobra por domiciliación; el resto de formas de pago no entran en remesa. */
    public function scopeDomiciliado($query)
    {
        return $query->where('forma_de_pago_id', FormaDePago::RECIBO_BANCARIO);
    }

    public function estaPagado(): bool
    {
        return (float) $this->saldo <= 0;
    }
}

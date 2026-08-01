<?php

namespace App\Models;

use App\Models\Traits\ConHistorialEstado;
use Illuminate\Database\Eloquent\Model;

class Presupuesto extends Model
{
    use ConHistorialEstado;

    protected $table = 'presupuestos';

    protected $fillable = [
        'comunidad_id',
        'nombre',
        'anho',
        'estado_id',
        'numero_pagos',
        'fecha_primer_pago',
        'periodicidad_id',
    ];

    protected $casts = [
        'fecha_primer_pago' => 'date',
    ];

    public function comunidad()
    {
        return $this->belongsTo(Comunidad::class);
    }

    public function estado()
    {
        return $this->belongsTo(TipoEstadoPresupuesto::class, 'estado_id');
    }

    public function periodicidad()
    {
        return $this->belongsTo(TipoPeriodicidadPago::class, 'periodicidad_id');
    }

    public function conceptos()
    {
        return $this->hasMany(ConceptoPresupuesto::class);
    }

    /**
     * Con un presupuesto de un año (12 meses) y una periodicidad de $meses entre pago y
     * pago, el número de pagos no es un dato aparte: sale de dividir uno entre otro.
     */
    public static function numeroPagosPara(int $meses): int
    {
        return max(1, intdiv(12, $meses));
    }
}

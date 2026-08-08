<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una salida de dinero sobre una factura de proveedor.
 *
 * No se corrige ni se borra: para deshacer un pago se registra otro por el importe
 * contrario, igual que en Cobro, y así la fecha de cada movimiento queda como fue.
 */
class PagoFactura extends Model
{
    protected $table = 'pagos_facturas';

    protected $fillable = [
        'factura_proveedor_id',
        'cuenta_bancaria_id',
        'fecha',
        'importe',
        'asiento_contable',
    ];

    protected $casts = [
        'fecha'   => 'date',
        'importe' => 'decimal:2',
    ];

    public function factura()
    {
        return $this->belongsTo(FacturaProveedor::class, 'factura_proveedor_id');
    }

    public function cuentaBancaria()
    {
        return $this->belongsTo(CuentaBancaria::class, 'cuenta_bancaria_id');
    }
}

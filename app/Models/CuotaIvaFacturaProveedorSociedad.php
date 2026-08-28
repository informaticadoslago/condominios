<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuotaIvaFacturaProveedorSociedad extends Model
{
    protected $table = 'cuotas_iva_facturas_proveedores_sociedad';

    protected $fillable = [
        'factura_proveedor_sociedad_id',
        'tipo_iva',
        'importe',
    ];

    protected $casts = [
        'tipo_iva' => 'decimal:2',
        'importe'  => 'decimal:2',
    ];

    public function factura()
    {
        return $this->belongsTo(FacturaProveedorSociedad::class, 'factura_proveedor_sociedad_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Nº de factura/fecha/base/total de un documento (tipo Factura) ya adjuntado a un proveedor de sociedad. */
class FacturaProveedorSociedad extends Model
{
    protected $table = 'facturas_proveedores_sociedad';

    protected $fillable = [
        'documento_id',
        'proveedor_id',
        'numero_factura',
        'fecha_factura',
        'importe_base',
        'importe_total',
    ];

    protected $casts = [
        'importe_base'  => 'decimal:2',
        'importe_total' => 'decimal:2',
    ];

    public function documento()
    {
        return $this->belongsTo(Documento::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function cuotasIva()
    {
        return $this->hasMany(CuotaIvaFacturaProveedorSociedad::class, 'factura_proveedor_sociedad_id');
    }
}

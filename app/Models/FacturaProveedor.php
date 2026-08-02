<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Nº de factura/fecha/importe de un documento (tipo Factura) ya adjuntado a un proveedor. */
class FacturaProveedor extends Model
{
    protected $table = 'facturas_proveedores';

    protected $fillable = [
        'documento_id',
        'proveedor_id',
        'numero_factura',
        'fecha_factura',
        'importe',
    ];

    protected $casts = [
        'importe' => 'decimal:2',
    ];

    public function documento()
    {
        return $this->belongsTo(Documento::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }
}

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
        'actividad_id',
        'numero_factura',
        'fecha_factura',
        'importe',
        'importe_pagado',
        'cuenta_gasto',
        'asiento_contable',
    ];

    protected $casts = [
        'importe'        => 'decimal:2',
        'importe_pagado' => 'decimal:2',
    ];

    /** Lo que queda por pagar de esta factura. */
    public function pendiente(): float
    {
        return round((float) $this->importe - (float) $this->importe_pagado, 2);
    }

    public function pagos()
    {
        return $this->hasMany(PagoFactura::class, 'factura_proveedor_id');
    }

    /**
     * Queda algo de esta factura por llevar a la contabilidad: ella misma, o alguno de sus
     * pagos que se quedó sin asiento porque la contabilidad falló en ese momento.
     *
     * En los listados se usa el contador `pagos_sin_asentar_count` que carga la consulta,
     * para no preguntar por cada fila.
     */
    public function faltaPorContabilizar(): bool
    {
        if ($this->asiento_contable === null) {
            return true;
        }

        return ($this->pagos_sin_asentar_count ?? $this->pagos()->whereNull('asiento_contable')->count()) > 0;
    }

    public function documento()
    {
        return $this->belongsTo(Documento::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function actividad()
    {
        return $this->belongsTo(Actividad::class);
    }
}

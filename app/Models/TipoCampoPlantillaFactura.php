<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCampoPlantillaFactura extends Model
{
    protected $table = 'tipo_campo_plantilla_facturas';

    const
    NUMERO_FACTURA = 1,
    FECHA = 2,
    IMPORTE = 3,
    CIF = 4,
    RAZON_SOCIAL = 5;

    protected $fillable = ['descripcion'];
}

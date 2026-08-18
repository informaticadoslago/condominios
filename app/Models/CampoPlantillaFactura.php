<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampoPlantillaFactura extends Model
{
    protected $table = 'campos_plantillas_facturas';

    protected $fillable = [
        'plantilla_factura_id',
        'tipo_campo_plantilla_factura_id',
        'texto_ancla',
        'valor_ejemplo',
        'delta_columna',
        'delta_lineas',
        'longitud_valor',
        'pagina',
        'pos_x',
        'pos_y',
        'pos_ancho',
    ];

    public function plantillaFactura()
    {
        return $this->belongsTo(PlantillaFactura::class);
    }

    public function tipoCampo()
    {
        return $this->belongsTo(TipoCampoPlantillaFactura::class, 'tipo_campo_plantilla_factura_id');
    }
}

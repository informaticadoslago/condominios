<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A qué se dedica un proveedor, y con ello a qué cuenta de gasto van sus facturas. */
class TipoProveedor extends Model
{
    protected $table = 'tipo_proveedores';

    const
    REPARACION = 1,
    PROFESIONALES = 2,
    SUMINISTROS = 3,
    LIMPIEZA = 4,
    SEGUROS = 5;

    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA = 2;

    protected $fillable = ['descripcion', 'cuenta_gasto', 'estado_id'];

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }

    public function scopeActivo($query)
    {
        return $query->where('estado_id', self::ESTADO_ACTIVO);
    }
}

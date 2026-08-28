<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A qué se dedica un proveedor de sociedad, y con ello a qué cuenta de gasto/compra van
 * sus facturas. Subgrupo 60 (Compras) => el asiento va contra el pasivo 400 (proveedor);
 * cualquier otra (62x, servicios exteriores) => contra 410 (acreedor). Ver
 * docs/plan-de-cuentas.md.
 */
class TipoProveedorSociedad extends Model
{
    protected $table = 'tipo_proveedores_sociedad';

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

    /** El asiento va contra 400 (proveedor) si compra (subgrupo 60); si no, contra 410 (acreedor). */
    public function esDeCompras(): bool
    {
        return str_starts_with($this->cuenta_gasto, '60');
    }
}

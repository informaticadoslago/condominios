<?php

namespace App\Models;

use App\Models\Traits\ConDocumentos;
use App\Models\Traits\ConHistorialEstado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Proveedor extends Model
{
    use ConDocumentos;
    use ConHistorialEstado;

    const
        ESTADO_ACTIVO = 1,
        ESTADO_BAJA = 2;

    protected $table = 'proveedores';

    protected $fillable = [
        'persona_comunidad_id',
        'estado_id',
    ];

    public function persona()
    {
        return $this->belongsTo(PersonaComunidad::class, 'persona_comunidad_id');
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    public function cuentasBancarias(): MorphMany
    {
        return $this->morphMany(CuentaBancaria::class, 'titular');
    }

    public function facturas()
    {
        return $this->hasMany(FacturaProveedor::class);
    }
}

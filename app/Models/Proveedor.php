<?php

namespace App\Models;

use App\Models\Traits\ConDocumentos;
use App\Models\Traits\ConHistorialEstado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Proveedor extends Model
{
    use ConDocumentos;
    use ConHistorialEstado;

    const
        ESTADO_ACTIVO = 1,
        ESTADO_BAJA = 2;

    protected $table = 'proveedores';

    protected $fillable = [
        'persona_type',
        'persona_id',
        'tipo_type',
        'tipo_id',
        'estado_id',
    ];

    public function persona(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeDeComunidad($query, $comunidadId)
    {
        return $query->whereHasMorph('persona', [PersonaComunidad::class],
            fn ($q) => $q->where('comunidad_id', $comunidadId));
    }

    public function scopeDeSociedad($query, $sociedadId)
    {
        return $query->whereHasMorph('persona', [PersonaSociedad::class],
            fn ($q) => $q->where('sociedad_id', $sociedadId));
    }

    public function tipo(): MorphTo
    {
        return $this->morphTo();
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

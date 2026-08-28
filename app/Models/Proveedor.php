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

    public function facturasSociedad()
    {
        return $this->hasMany(FacturaProveedorSociedad::class, 'proveedor_id');
    }

    /** Este proveedor es de la comunidad/sociedad que hay ahora mismo en sesión (según de qué plugin sea). */
    public function perteneceAlContextoActivo(): bool
    {
        return match ($this->persona_type) {
            PersonaComunidad::class => $this->persona?->comunidad_id == session('comunidad_actual_id'),
            PersonaSociedad::class  => $this->persona?->sociedad_id == session('sociedad_actual_id'),
            default => false,
        };
    }
}

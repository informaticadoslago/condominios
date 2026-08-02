<?php

namespace App\Models;

use App\Models\Traits\ConHistorialEstado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Propietario extends Model
{
    use ConHistorialEstado;

    const
        ESTADO_ACTIVO = 1,
        ESTADO_BAJA = 2;

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

    /** Inmuebles de los que es propietario VIGENTE. Para el histórico, ver titularidades(). */
    public function inmuebles()
    {
        return $this->belongsToMany(Inmueble::class, 'titularidades')
            ->withPivot(['cuota_percent', 'causa', 'fecha_inicio', 'fecha_fin'])
            ->wherePivotNull('fecha_fin');
    }

    public function titularidades()
    {
        return $this->hasMany(Titularidad::class);
    }

    public function cuentasBancarias(): MorphMany
    {
        return $this->morphMany(CuentaBancaria::class, 'titular');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Propietario extends Model
{
    protected $fillable = [
        'persona_comunidad_id',
    ];

    public function persona()
    {
        return $this->belongsTo(PersonaComunidad::class, 'persona_comunidad_id');
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

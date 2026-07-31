<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inmueble extends Model
{
    protected $fillable = [
        'comunidad_id',
        'ocupacion_id',
        'tipo_inmueble_id',
        'planta',
        'puerta',
        'coeficiente',
        'referencia_catastral',
    ];

    protected $casts = [
        'coeficiente' => 'decimal:2',
    ];

    public function comunidad()
    {
        return $this->belongsTo(Comunidad::class);
    }

    public function ocupacion()
    {
        return $this->belongsTo(TipoOcupacion::class, 'ocupacion_id');
    }

    public function tipoInmueble()
    {
        return $this->belongsTo(TipoInmueble::class);
    }

    /** Propietarios VIGENTES (titularidad sin fecha_fin). Para el histórico, ver titularidades(). */
    public function propietarios()
    {
        return $this->belongsToMany(Propietario::class, 'titularidades')
            ->withPivot(['cuota_percent', 'causa', 'fecha_inicio', 'fecha_fin'])
            ->wherePivotNull('fecha_fin');
    }

    public function titularidades()
    {
        return $this->hasMany(Titularidad::class);
    }

    public function gruposDeReparto()
    {
        return $this->belongsToMany(GrupoDeReparto::class, 'inmueble_grupo_de_reparto', 'inmueble_id', 'grupo_de_reparto_id');
    }
}

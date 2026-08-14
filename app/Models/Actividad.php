<?php

namespace App\Models;

use App\Services\Actividades\EnlaceContableActividad;
use Illuminate\Database\Eloquent\Model;

class Actividad extends Model
{
    protected $table = 'actividades';

    protected $fillable = [
        'comunidad_id',
        'nombre',
        'proyecto_contable_id',
    ];

    /**
     * Si la comunidad ya lleva contabilidad, la actividad estrena su proyecto en el acto;
     * si no, no hace nada y queda pendiente del botón "Enlace contabilidad" de la
     * comunidad, que recorre las que falten (ver Comunidades\Lista::ejecutarEnlace()).
     */
    protected static function booted(): void
    {
        static::created(function (self $actividad) {
            app(EnlaceContableActividad::class)->asignarProyecto($actividad);
        });
    }

    public function comunidad()
    {
        return $this->belongsTo(Comunidad::class);
    }

    public function presupuestos()
    {
        return $this->hasMany(Presupuesto::class);
    }
}

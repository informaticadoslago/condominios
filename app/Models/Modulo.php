<?php

namespace App\Models;

use App\Models\Traits\ConHistorialEstado;
use Illuminate\Database\Eloquent\Model;

/** Módulo a estudiar de un horario: agrupa asignaturas. */
class Modulo extends Model
{
    use ConHistorialEstado;

    const
        ESTADO_ACTIVO = 1,
        ESTADO_BAJA = 2;

    protected $table = 'modulos';

    protected $fillable = [
        'horario_id',
        'nombre',
        'estado_id',
    ];

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    public function asignaturas()
    {
        return $this->hasMany(Asignatura::class);
    }
}

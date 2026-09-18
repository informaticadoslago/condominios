<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorarioSesion extends Model
{
    protected $table = 'horario_sesiones';

    protected $fillable = [
        'horario_id',
        'dia_semana',
        'sesion_numero',
        'asignatura_id',
    ];

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    public function asignatura()
    {
        return $this->belongsTo(Asignatura::class);
    }
}

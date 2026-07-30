<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstadoAlumno extends Model
{
    protected $table = 'estado_alumnos';

    public $timestamps = false;

    const
    ALUMNO_ACTIVO = 1,
    ALUMNO_BAJA = 2;
}

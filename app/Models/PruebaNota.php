<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Nota de un alumno (por su apunte en la asignatura) en una prueba. */
class PruebaNota extends Model
{
    protected $table = 'prueba_evaluable_notas';

    protected $fillable = [
        'prueba_id',
        'asignatura_alumno_id',
        'nota',
        'nota_sobre_10',
    ];

    protected $casts = [
        'nota'          => 'float',
        'nota_sobre_10' => 'float',
    ];

    public function prueba()
    {
        return $this->belongsTo(Prueba::class);
    }

    public function apunte()
    {
        return $this->belongsTo(AsignaturaAlumno::class, 'asignatura_alumno_id');
    }
}

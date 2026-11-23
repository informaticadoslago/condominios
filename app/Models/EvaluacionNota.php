<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Retoque a mano de la nota de un alumno (por su apunte en la asignatura) en una
 * evaluación: media es la media de la que partía y nota la puesta a mano. Ver
 * App\Services\Horarios\CuadroEvaluacion.
 */
class EvaluacionNota extends Model
{
    protected $table = 'evaluacion_notas';

    protected $fillable = [
        'evaluacion_id',
        'asignatura_alumno_id',
        'media',
        'nota',
    ];

    protected $casts = [
        'media' => 'float',
        'nota'  => 'float',
    ];

    public function evaluacion()
    {
        return $this->belongsTo(Evaluacion::class);
    }

    public function apunte()
    {
        return $this->belongsTo(AsignaturaAlumno::class, 'asignatura_alumno_id');
    }
}

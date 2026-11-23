<?php

namespace App\Models;

use App\Models\Traits\ConHistorialEstado;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/** Apunte de un alumno en una asignatura, con el grupo en el que la cursa. */
class AsignaturaAlumno extends Model
{
    use ConHistorialEstado;

    const
        ESTADO_ACTIVO = 1,
        ESTADO_BAJA = 2;

    protected $table = 'asignatura_alumnos';

    protected $fillable = [
        'asignatura_id',
        'alumno_id',
        'grupo',
        'estado_id',
    ];

    public function asignatura()
    {
        return $this->belongsTo(Asignatura::class);
    }

    public function alumno()
    {
        return $this->belongsTo(Alumno::class);
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    public function notas()
    {
        return $this->hasMany(PruebaNota::class);
    }

    /** Sus notas de evaluación retocadas a mano (ver CuadroEvaluacion). */
    public function notasEvaluacion()
    {
        return $this->hasMany(EvaluacionNota::class);
    }

    /** Título del historial de estados (ver ConHistorialEstadoModal). */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn () => $this->alumno?->nombreCompleto);
    }

    /**
     * Borrado físico del apunte, con su historial de estados. Un alumno no se queda sin
     * ninguna asignatura: si este era su último apunte, se borra también el alumno. Con
     * notas no se borra (ver Alumnos\Lista); sin ellas, los retoques de la nota de una
     * evaluación que queden ya no valen y se van con él.
     */
    public function borrarDefinitivamente(): void
    {
        $alumno = $this->alumno;

        $this->historialEstados()->delete();
        $this->notasEvaluacion()->delete();
        $this->delete();

        if ($alumno && ! $alumno->apuntes()->exists()) {
            $alumno->delete();
        }
    }
}

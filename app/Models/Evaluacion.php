<?php

namespace App\Models;

use App\Models\Traits\ConHistorialEstado;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/** Evaluación de un curso: un periodo con nombre dentro de las fechas del curso. */
class Evaluacion extends Model
{
    use ConHistorialEstado;

    const
        ESTADO_ACTIVO = 1,
        ESTADO_BAJA = 2;

    protected $table = 'evaluaciones';

    protected $fillable = [
        'curso_id',
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'estado_id',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];

    public function curso()
    {
        return $this->belongsTo(Curso::class);
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    /** Título del historial de estados (ver ConHistorialEstadoModal). */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn () => $this->nombre);
    }

    public function pruebas()
    {
        return $this->hasMany(Prueba::class);
    }

    /** Las notas de la evaluación retocadas a mano (ver CuadroEvaluacion). */
    public function notas()
    {
        return $this->hasMany(EvaluacionNota::class);
    }

    protected static function booted(): void
    {
        // Una evaluación con pruebas no se borra (ver Evaluaciones\Lista::enUso); sin ellas,
        // los retoques que queden ya no valen (no hay media de la que partan) y se van con ella.
        static::deleting(function (self $evaluacion) {
            $evaluacion->notas()->delete();
        });
    }

    /** La evaluación activa del curso en la que cae hoy, si la hay. */
    public static function actual(int $cursoId): ?self
    {
        return self::where('curso_id', $cursoId)
            ->where('estado_id', self::ESTADO_ACTIVO)
            ->whereDate('fecha_inicio', '<=', today())
            ->whereDate('fecha_fin', '>=', today())
            ->first();
    }

    /** Si en el curso hay otra evaluación, activa o no, con algún día dentro de esas fechas. */
    public static function seSolapa(int $cursoId, string $inicio, string $fin, ?int $ignorarId = null): bool
    {
        return self::where('curso_id', $cursoId)
            ->whereDate('fecha_inicio', '<=', $fin)
            ->whereDate('fecha_fin', '>=', $inicio)
            ->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId))
            ->exists();
    }
}

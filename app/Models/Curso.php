<?php

namespace App\Models;

use App\Models\Traits\ConHistorialEstado;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/** Curso de un horario de profesor: un periodo con nombre, dividido en evaluaciones. */
class Curso extends Model
{
    use ConHistorialEstado;

    const
        ESTADO_ACTIVO = 1,
        ESTADO_BAJA = 2;

    protected $table = 'cursos';

    protected $fillable = [
        'horario_id',
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'estado_id',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    public function evaluaciones()
    {
        return $this->hasMany(Evaluacion::class);
    }

    /** Título del historial de estados (ver ConHistorialEstadoModal). */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn () => $this->nombre);
    }

    /**
     * Si en el horario hay otro curso, activo o no, con algún día dentro de esas fechas:
     * entre dos fechas solo puede haber un curso.
     */
    public static function seSolapa(int $horarioId, string $inicio, string $fin, ?int $ignorarId = null): bool
    {
        return self::where('horario_id', $horarioId)
            ->whereDate('fecha_inicio', '<=', $fin)
            ->whereDate('fecha_fin', '>=', $inicio)
            ->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId))
            ->exists();
    }
}

<?php

namespace App\Models;

use App\Models\Traits\ConHistorialEstado;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/** Prueba evaluable de una asignatura en una evaluación. */
class Prueba extends Model
{
    use ConHistorialEstado;

    const
        ESTADO_ACTIVO = 1,
        ESTADO_BAJA = 2;

    protected $table = 'pruebas';

    protected $fillable = [
        'asignatura_id',
        'evaluacion_id',
        'nombre',
        'fecha',
        'puntuacion_maxima',
        'peso',
        'orden',
        'estado_id',
    ];

    protected $casts = [
        'fecha'             => 'date',
        'puntuacion_maxima' => 'float',
        'peso'              => 'float',
    ];

    public function asignatura()
    {
        return $this->belongsTo(Asignatura::class);
    }

    public function evaluacion()
    {
        return $this->belongsTo(Evaluacion::class);
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    public function notas()
    {
        return $this->hasMany(PruebaNota::class);
    }

    /** La prueba nueva va al final del orden de su asignatura (ver Pruebas\Lista::mover). */
    protected static function booted(): void
    {
        static::creating(function (self $prueba) {
            $prueba->orden ??= (int) self::where('asignatura_id', $prueba->asignatura_id)->max('orden') + 1;
        });
    }

    /** Título del historial de estados (ver ConHistorialEstadoModal). */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn () => $this->nombre);
    }

    /** La nota sobre 10 que corresponde a una nota sobre la puntuación máxima. */
    public function sobre10(float $nota): float
    {
        return round($nota * 10 / $this->puntuacion_maxima, 2);
    }

    /** Un número sin ceros de más: 30 y no 30.00, 7,5 y no 7.50. */
    public static function numero(float|string|null $valor): string
    {
        return $valor === null || $valor === '' ? '' : str_replace('.', ',', (string) round((float) $valor, 2));
    }
}

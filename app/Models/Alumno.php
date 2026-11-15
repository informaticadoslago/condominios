<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Alumno de un horario de profesor. Se apunta a las asignaturas del horario con
 * AsignaturaAlumno, que es quien lleva el grupo y el estado.
 */
class Alumno extends Model
{
    protected $table = 'alumnos';

    protected $fillable = [
        'horario_id',
        'nombre',
        'apellido1',
        'apellido2',
        'fecha_nacimiento',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
    ];

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    public function apuntes()
    {
        return $this->hasMany(AsignaturaAlumno::class);
    }

    /** «Apellido1 Apellido2, Nombre», como se ordena la lista. */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->apellido1} {$this->apellido2}").", {$this->nombre}");
    }

    /**
     * El alumno del horario con esos cuatro datos, si existe: con los cuatro iguales es
     * el mismo alumno. Compara con la intercalación de las columnas, igual que el índice
     * único: sin distinguir mayúsculas ni acentos.
     */
    public static function buscarIgual(int $horarioId, string $nombre, string $apellido1, string $apellido2, string $fechaNacimiento): ?self
    {
        return self::where('horario_id', $horarioId)
            ->where('nombre', $nombre)
            ->where('apellido1', $apellido1)
            ->where('apellido2', $apellido2)
            ->whereDate('fecha_nacimiento', $fechaNacimiento)
            ->first();
    }
}

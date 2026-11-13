<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoEvento extends Model
{
    /** El código distingue el examen por id: es el único tipo que pide asignatura. */
    const EXAMEN = 1;

    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA = 2;

    protected $table = 'tipo_eventos';

    protected $fillable = ['descripcion', 'estado_id'];

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }

    public function scopeActivo($query)
    {
        return $query->where('estado_id', self::ESTADO_ACTIVO);
    }

    public function eventos()
    {
        return $this->hasMany(Evento::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoHorario extends Model
{
    /** El código distingue los dos tipos por id (el de profesor tiene rejilla propia). */
    const
    ALUMNO = 1,
    PROFESOR = 2;

    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA = 2;

    protected $table = 'tipo_horarios';

    protected $fillable = ['descripcion', 'estado_id'];

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }

    public function scopeActivo($query)
    {
        return $query->where('estado_id', self::ESTADO_ACTIVO);
    }

    public function horarios()
    {
        return $this->hasMany(Horario::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoDirectivoComunidad extends Model
{
    protected $table = 'tipo_directivo_comunidades';

    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA = 2;

    protected $fillable = ['descripcion', 'estado_id'];

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }

    public function scopeActivo($query)
    {
        return $query->where('estado_id', self::ESTADO_ACTIVO);
    }

    public function directivos()
    {
        return $this->hasMany(ComunidadDirectivo::class);
    }
}

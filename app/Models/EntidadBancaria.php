<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntidadBancaria extends Model
{
    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA = 2;

    protected $table = 'entidades_bancarias';

    public $timestamps = false;

    protected $fillable = ['codigo', 'descripcion', 'bic', 'estado_id'];

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }

    public function scopeActivo($query)
    {
        return $query->where('estado_id', self::ESTADO_ACTIVO);
    }
}

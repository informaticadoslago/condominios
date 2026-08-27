<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SociedadCliente extends Model
{
    protected $table = 'sociedad_clientes';

    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA = 2;

    protected $fillable = [
        'sociedad_id',
        'persona_sociedad_id',
        'estado_id',
    ];

    public function sociedad()
    {
        return $this->belongsTo(Sociedad::class);
    }

    public function persona()
    {
        return $this->belongsTo(PersonaSociedad::class, 'persona_sociedad_id');
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }

    public function scopeActiva($query)
    {
        return $query->where('estado_id', self::ESTADO_ACTIVO);
    }
}

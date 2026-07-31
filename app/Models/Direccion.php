<?php

namespace App\Models;

use App\Models\Traits\ConCopiaAlBorrar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Direccion extends Model
{
    use ConCopiaAlBorrar;

    protected $table = 'direcciones';

    protected $fillable = [
        'tipo_direccion_id',
        'direccion1',
        'via_id',
        'numero',
        'portal',
        'piso',
        'puerta',
        'barrio',
        'pais_id',
        'codigo_postal',
        'provincia_id',
        'municipio_id',
        'poblacion_id',
        'provincia',
        'municipio',
        'poblacion',
        'estado_id',
    ];

    public function direccionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeActivo($query)
    {
        return $query->where('estado_id', Estado::ESTADO_ACTIVO);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstadoPersona extends Model
{
    protected $table = 'estado_personas';

    public $timestamps = false;

    // Ids legacy conservados (el 3 'Inicial' es de usuarios, no existe aquí)
    const
    PERSONA_ACTIVA = 1,
    PERSONA_INACTIVA = 2,
    PERSONA_ANONIMA = 4;
}

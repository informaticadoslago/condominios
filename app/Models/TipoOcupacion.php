<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoOcupacion extends Model
{
    protected $table = 'tipo_ocupaciones';

    public $timestamps = false;

    const
    ALQUILADO = 1,
    PROPIETARIO = 2;
}

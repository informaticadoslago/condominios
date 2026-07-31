<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoInmueble extends Model
{
    protected $table = 'tipo_inmuebles';

    public $timestamps = false;

    const
    PISO = 1,
    GARAJE = 2;
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstadoSocio extends Model
{
    public $timestamps = false;

    const
    SOCIO_ACTIVO = 1,
    SOCIO_INACTIVO = 2;


}

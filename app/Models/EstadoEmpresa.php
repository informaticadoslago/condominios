<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstadoEmpresa extends Model
{
    public $timestamps = false;

    const
    EMPRESA_ACTIVO = 1,
    EMPRESA_BAJA = 2;
}

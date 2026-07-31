<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCuentaContable extends Model
{
    protected $table = 'tipo_cuenta_contables';

    public $timestamps = false;

    const
    ACTIVO = 1,
    PASIVO = 2,
    PATRIMONIO_NETO = 3,
    INGRESO = 4,
    GASTO = 5;
}

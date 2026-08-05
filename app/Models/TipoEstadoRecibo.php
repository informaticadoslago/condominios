<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoEstadoRecibo extends Model
{
    protected $table = 'tipo_estado_recibos';

    public $timestamps = false;

    const
    GENERADO = 1,
    ENVIADO = 2,
    COBRADO = 3,
    DEVUELTO = 4;

    protected $fillable = ['descripcion'];
}

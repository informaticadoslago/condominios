<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstadoDireccionSociedad extends Model
{
    protected $table = 'estado_direccion_sociedades';

    public $timestamps = false;

    const
    SEDE = 1,
    DOMICILIO_SOCIAL = 2;
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoContacto extends Model
{
    public $timestamps = false;
    protected $table = 'tipo_contactos';

    const
    MOVIL    = 1,
    TELEFONO = 2,
    FAX      = 3,
    EMAIL    = 4;
}

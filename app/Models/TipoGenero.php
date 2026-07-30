<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Database\Eloquent\Builder;

class TipoGenero extends Model
{

    public $timestamps = false;

    protected $table = 'tipo_generos';

    const
    GENERO_HOMBRE = 1,
    GENERO_MUJER = 2,
    GENERO_OTRO = 3;
    
    protected $fillable = [
        'nombre', 
    ];

}

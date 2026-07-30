<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estado extends Model
{
    use HasFactory;
    
    public $timestamps=false;
    
    const
    ESTADO_ACTIVO = 1,
    ESTADO_INACTIVO = 2,
    ESTADO_EN_PREPARACION = 3;

}

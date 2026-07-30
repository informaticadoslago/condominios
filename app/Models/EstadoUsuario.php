<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstadoUsuario extends Model
{
    public $timestamps = false;

    const
    USUARIO_ACTIVO   = 1,
    USUARIO_INACTIVO = 2,
    USUARIO_INICIAL  = 3;

    // public function usuarios()
    // {
    //     return $this->hasMany(User::class);
    // }
}

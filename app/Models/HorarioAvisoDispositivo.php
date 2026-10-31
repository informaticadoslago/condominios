<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un dispositivo al que un usuario quiere los avisos de sesión de un horario (ver
 * HorarioSesionAviso). Si el usuario no tiene ninguno en ese horario, los avisos van a
 * todos sus dispositivos, también a los que añada después; si tiene alguno, solo a esos.
 * Se elige en "Configuración avisos" del horario semanal.
 */
class HorarioAvisoDispositivo extends Model
{
    protected $table = 'horario_aviso_dispositivos';

    protected $fillable = [
        'user_id',
        'horario_id',
        'push_subscription_id',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Alta a medias de un wizard, pendiente de terminar. El "payload" es libre por
 * tipo: para Inmuebles solo guarda el id del inmueble ya creado en el paso 1
 * (que sigue siendo un registro real), no un formulario entero en JSON.
 */
class Borrador extends Model
{
    protected $table = 'borradores';

    const TIPO_INMUEBLE = 'inmueble';
    const TIPO_PROPIETARIO = 'propietario';

    protected $fillable = [
        'user_id',
        'tipo',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function scopeDeTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeDelUsuario($query, ?int $userId = null)
    {
        return $query->where('user_id', $userId ?? auth()->id());
    }
}

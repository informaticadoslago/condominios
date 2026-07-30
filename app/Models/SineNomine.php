<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Copia de seguridad de los registros borrados físicamente (tabla 'sine_nomines').
 * La escribe el trait ConCopiaAlBorrar en el evento 'deleting' del modelo: guarda
 * sus columnas serializadas (cifradas por defecto) y su hash, para poder auditarlo
 * o recuperarlo.
 */
class SineNomine extends Model
{
    protected $table = 'sine_nomines';

    protected $fillable = [
        'user_id',
        'modelo',
        'registro',
        'hash_registro',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

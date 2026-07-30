<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Preferencias de un usuario en una lista (filtros, orden y líneas por página).
 * Se guardan solas al tocar cualquiera de las tres cosas y se recuperan al
 * volver a entrar en la lista. Borrar filtro = borrar la fila.
 */
class PreferenciaLista extends Model
{
    protected $table = 'preferencias_listas';

    protected $fillable = [
        'user_id',
        'lista',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function scopeDelUsuario($query)
    {
        return $query->where('user_id', auth()->id());
    }

    /** Preferencias guardadas del usuario actual en una lista, o null si no hay. */
    public static function recordar(string $lista): ?array
    {
        if (! auth()->check()) {
            return null;
        }

        return static::delUsuario()->where('lista', $lista)->value('payload');
    }

    public static function guardar(string $lista, array $payload): void
    {
        if (! auth()->check()) {
            return;
        }

        static::updateOrCreate(
            ['user_id' => auth()->id(), 'lista' => $lista],
            ['payload' => $payload],
        );
    }

    public static function olvidar(string $lista): void
    {
        if (! auth()->check()) {
            return;
        }

        static::delUsuario()->where('lista', $lista)->delete();
    }
}

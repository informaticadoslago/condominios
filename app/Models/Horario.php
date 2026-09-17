<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class Horario extends Model
{
    protected $table = 'horarios';

    protected $fillable = ['nombre'];

    /** Nombre del rol de acceso a este horario (puerta de entrada, no permisos). */
    public function nombreRol(): string
    {
        return 'horario-'.$this->id;
    }

    protected static function booted(): void
    {
        static::created(function (self $horario) {
            Role::firstOrCreate(['name' => $horario->nombreRol(), 'guard_name' => 'web']);
        });
    }
}

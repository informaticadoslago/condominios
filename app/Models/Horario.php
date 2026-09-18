<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class Horario extends Model
{
    protected $table = 'horarios';

    protected $fillable = ['nombre', 'duracion_sesion_minutos'];

    /** Nombre del rol de acceso a este horario (puerta de entrada, no permisos). */
    public function nombreRol(): string
    {
        return 'horario-'.$this->id;
    }

    public function dias()
    {
        return $this->hasMany(HorarioDia::class);
    }

    public function sesiones()
    {
        return $this->hasMany(HorarioSesion::class);
    }

    protected static function booted(): void
    {
        static::created(function (self $horario) {
            Role::firstOrCreate(['name' => $horario->nombreRol(), 'guard_name' => 'web']);
        });
    }
}

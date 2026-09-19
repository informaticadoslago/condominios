<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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

    public function jornadas()
    {
        return $this->hasMany(Jornada::class);
    }

    public function sesiones()
    {
        return $this->hasMany(HorarioSesion::class);
    }

    /**
     * [dia_semana => Collection<Jornada>], solo para los días que tienen alguna
     * jornada asignada, ordenado por día. Requiere la relación 'jornadas' cargada.
     *
     * @return Collection<int, Collection<int, Jornada>>
     */
    public function jornadasPorDia(): Collection
    {
        $porDia = [];

        foreach ($this->jornadas as $jornada) {
            foreach ($jornada->dias_semana as $dia) {
                $porDia[$dia][] = $jornada;
            }
        }

        ksort($porDia);

        return collect($porDia)->map(fn (array $jornadas) => collect($jornadas));
    }

    protected static function booted(): void
    {
        static::created(function (self $horario) {
            Role::firstOrCreate(['name' => $horario->nombreRol(), 'guard_name' => 'web']);
        });
    }
}

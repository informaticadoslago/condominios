<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Permission\Models\Role;

class Comunidad extends Model
{
    protected $table = 'comunidades';

    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA = 2;

    protected $fillable = [
        'persona_id',
        'estado_id',
    ];

    protected $with = ['persona'];

    /** Nombre del rol de acceso a esta comunidad (puerta de entrada, no permisos). */
    public function nombreRol(): string
    {
        return 'comunidad-'.$this->id;
    }

    protected static function booted(): void
    {
        static::created(function (self $comunidad) {
            Role::firstOrCreate(['name' => $comunidad->nombreRol(), 'guard_name' => 'web']);
        });
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class, 'estado_id');
    }

    public function scopeActiva($query)
    {
        return $query->where('estado_id', self::ESTADO_ACTIVO);
    }

    public function getNombreAttribute()
    {
        return $this->persona?->razon_social;
    }

    public function getCifAttribute()
    {
        return $this->persona?->documento_identificativo;
    }

    public function inmuebles()
    {
        return $this->hasMany(Inmueble::class);
    }

    public function directivos()
    {
        return $this->hasMany(ComunidadDirectivo::class);
    }

    public function gruposDeReparto()
    {
        return $this->hasMany(GrupoDeReparto::class);
    }

    public function cuentasBancarias(): MorphMany
    {
        return $this->morphMany(CuentaBancaria::class, 'titular');
    }
}

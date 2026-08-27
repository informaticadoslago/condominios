<?php

namespace App\Models;

use App\Models\Traits\ConDocumentos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Permission\Models\Role;

class Sociedad extends Model
{
    use ConDocumentos;

    protected $table = 'sociedades';

    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA = 2;

    protected $fillable = [
        'persona_id',
        'estado_id',
        'empresa_contable_id',
    ];

    protected $with = ['persona'];

    /** Nombre del rol de acceso a esta sociedad (puerta de entrada, no permisos). */
    public function nombreRol(): string
    {
        return 'sociedad-'.$this->id;
    }

    protected static function booted(): void
    {
        static::created(function (self $sociedad) {
            Role::firstOrCreate(['name' => $sociedad->nombreRol(), 'guard_name' => 'web']);
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

    public function cuentasBancarias(): MorphMany
    {
        return $this->morphMany(CuentaBancaria::class, 'titular');
    }

    public function direcciones()
    {
        return $this->hasMany(SociedadDireccion::class);
    }

    public function domicilioSocial()
    {
        return $this->hasOne(SociedadDireccion::class)->where('estado_id', EstadoDireccionSociedad::DOMICILIO_SOCIAL);
    }

    public function centrosDeTrabajo()
    {
        return $this->hasMany(SociedadDireccion::class)->centrosDeTrabajo();
    }

    public function personas()
    {
        return $this->hasMany(PersonaSociedad::class);
    }

    public function clientes()
    {
        return $this->hasMany(SociedadCliente::class);
    }

    public function proveedores()
    {
        return $this->hasMany(SociedadProveedor::class);
    }

    public function trabajadores()
    {
        return $this->hasMany(SociedadTrabajador::class);
    }
}

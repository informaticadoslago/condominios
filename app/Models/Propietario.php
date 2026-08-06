<?php

namespace App\Models;

use App\Models\Traits\ConHistorialEstado;
use App\Services\Comunidades\EnlaceContableComunidad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Propietario extends Model
{
    use ConHistorialEstado;

    const
        ESTADO_ACTIVO = 1,
        ESTADO_BAJA = 2;

    protected $fillable = [
        'persona_comunidad_id',
        'estado_id',
        // Su subcuenta de cliente en la contabilidad; la pone EnlaceContableComunidad.
        'cuenta_contable',
    ];

    /**
     * Se engancha aquí, y no en cada asistente de alta, porque un propietario nace en
     * varios sitios (su propio alta, el alta de un inmueble) y en todos tiene que
     * acabar con su cuenta de cliente si la comunidad lleva contabilidad. Si no la
     * lleva, no hace nada.
     */
    protected static function booted(): void
    {
        static::created(function (self $propietario) {
            app(EnlaceContableComunidad::class)->asignarCuentaPropietario($propietario);
        });
    }

    public function persona()
    {
        return $this->belongsTo(PersonaComunidad::class, 'persona_comunidad_id');
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    /** Inmuebles de los que es propietario VIGENTE. Para el histórico, ver titularidades(). */
    public function inmuebles()
    {
        return $this->belongsToMany(Inmueble::class, 'titularidades')
            ->withPivot(['cuota_percent', 'causa', 'fecha_inicio', 'fecha_fin'])
            ->wherePivotNull('fecha_fin');
    }

    public function titularidades()
    {
        return $this->hasMany(Titularidad::class);
    }

    public function cuentasBancarias(): MorphMany
    {
        return $this->morphMany(CuentaBancaria::class, 'titular');
    }

    /**
     * Primera dirección de correo activa, para saber a dónde escribirle y si ya la ha
     * confirmado. Se resuelve sobre la relación ya cargada (with('persona.contactos'))
     * para que un listado no lance una consulta por fila.
     */
    public function correo(): ?Contacto
    {
        return $this->persona?->contactos
            ->where('tipo_contacto_id', TipoContacto::EMAIL)
            ->where('estado_id', Estado::ESTADO_ACTIVO)
            ->first();
    }
}

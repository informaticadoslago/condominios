<?php

namespace App\Models;

use App\Models\Traits\ConHistorialEstado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CuentaBancaria extends Model
{
    use ConHistorialEstado;

    const
        ESTADO_ACTIVA = 1,
        ESTADO_CANCELADA = 2;

    protected $table = 'cuentas_bancarias';

    protected $fillable = [
        'titular_type',
        'titular_id',
        'iban',
        'entidad_bancaria_id',
        'alias',
        'nombre_contable',
        'cuenta_contable',
        'persona_comunidad_id',
        'estado_id',
    ];

    public function titular(): MorphTo
    {
        return $this->morphTo();
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    public function formasPagoInmueble()
    {
        return $this->hasMany(FormaPagoInmueble::class);
    }

    /** Algún inmueble la tiene HOY como su forma de pago vigente: no se puede cancelar. */
    public function enUso(): bool
    {
        return $this->formasPagoInmueble()->vigente()->exists();
    }

    public function entidadBancaria()
    {
        return $this->belongsTo(EntidadBancaria::class);
    }

    /**
     * Titular REAL de la cuenta (quien firma). Normalmente es la propia persona del
     * titular (Propietario, Proveedor…); solo es otra persona distinta cuando el
     * titular es un propietario menor de edad, ver Propietarios\Crear\Steps\CuentaBancariaStep.
     */
    public function personaComunidad()
    {
        return $this->belongsTo(PersonaComunidad::class);
    }

    /**
     * Persona que figura como titular: la de personaComunidad si se indicó otra distinta
     * (propietario menor de edad), y si no la del propio titular.
     */
    public function titularReal(): ?PersonaComunidad
    {
        return $this->personaComunidad ?? $this->titular?->persona;
    }

    /** NIF del titular de la cuenta: es con lo que se numera su mandato SEPA. */
    public function nifTitular(): ?string
    {
        return $this->titularReal()?->documento_identificativo;
    }

    public function mandatosSepa()
    {
        return $this->hasMany(MandatoSepa::class);
    }

    /** El mandato ACTIVO de esta cuenta, si lo hay (uno cancelado no cuenta). */
    public function mandatoActivo()
    {
        return $this->hasOne(MandatoSepa::class)->where('estado_id', MandatoSepa::ESTADO_ACTIVO);
    }
}

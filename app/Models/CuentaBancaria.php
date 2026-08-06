<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CuentaBancaria extends Model
{
    protected $table = 'cuentas_bancarias';

    protected $fillable = [
        'titular_type',
        'titular_id',
        'iban',
        'entidad_bancaria_id',
        'alias',
        'persona_comunidad_id',
    ];

    public function titular(): MorphTo
    {
        return $this->morphTo();
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
}

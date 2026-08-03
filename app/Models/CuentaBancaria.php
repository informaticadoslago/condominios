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
}

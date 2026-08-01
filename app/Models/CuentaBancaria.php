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
    ];

    public function titular(): MorphTo
    {
        return $this->morphTo();
    }

    public function entidadBancaria()
    {
        return $this->belongsTo(EntidadBancaria::class);
    }
}

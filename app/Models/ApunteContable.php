<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApunteContable extends Model
{
    protected $table = 'apunte_contables';

    protected $fillable = [
        'asiento_contable_id', 'cuenta_contable_id', 'debe', 'haber', 'concepto',
    ];

    public function asientoContable()
    {
        return $this->belongsTo(AsientoContable::class);
    }

    public function cuentaContable()
    {
        return $this->belongsTo(CuentaContable::class);
    }
}

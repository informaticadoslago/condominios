<?php

namespace App\Models;

use App\Models\Traits\ConHistorialEstado;
use Illuminate\Database\Eloquent\Model;

class CuentaContable extends Model
{
    use ConHistorialEstado;

    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA = 2;

    protected $table = 'cuenta_contables';

    protected $fillable = [
        'tipo_cuenta_contable_id', 'cuenta_padre_id', 'codigo', 'nombre', 'estado_id',
    ];

    public function tipoCuentaContable()
    {
        return $this->belongsTo(TipoCuentaContable::class);
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    public function cuentaPadre()
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_padre_id');
    }

    public function subcuentas()
    {
        return $this->hasMany(CuentaContable::class, 'cuenta_padre_id');
    }
}

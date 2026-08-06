<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Clase de ingreso (cuotas, derramas): de qué grupo del plan cuelgan sus subcuentas.
 * El equivalente de TipoTerceroContable para el grupo 7.
 */
class TipoIngresoContable extends Model
{
    protected $table = 'tipo_ingreso_contables';

    protected $fillable = [
        'codigo', 'descripcion', 'prefijo_cuenta', 'estado_id',
    ];

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    /** Código de la cuenta de grupo de la que cuelgan las subcuentas de esta clase. */
    public function codigoCuentaGrupo(): string
    {
        return $this->prefijo_cuenta.'0000';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoTerceroContable extends Model
{
    protected $table = 'tipo_tercero_contables';

    protected $fillable = [
        'codigo', 'descripcion', 'prefijo_cuenta', 'estado_id',
    ];

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }

    public function tercerosContables()
    {
        return $this->hasMany(TerceroContable::class);
    }

    /** Código de la cuenta de grupo de la que cuelgan las subcuentas de este tipo. */
    public function codigoCuentaGrupo(): string
    {
        return $this->prefijo_cuenta.'0000';
    }
}

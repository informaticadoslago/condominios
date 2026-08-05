<?php

namespace App\Models;

use App\Models\Traits\ConHistorialEstado;
use Illuminate\Database\Eloquent\Model;

/**
 * Ficha fiscal de un tercero dentro de la contabilidad, con su subcuenta asociada.
 *
 * Los datos (NIF, razón social) son de la contabilidad, no una copia enlazada de nadie:
 * quien manda los asientos los envía, y a partir de ahí la contabilidad puede emitir sus
 * libros e informes sin preguntar a ningún otro módulo. El par sujeto_tipo/sujeto_id es
 * solo la etiqueta con la que quien llama reconoce a este tercero.
 */
class TerceroContable extends Model
{
    use ConHistorialEstado;

    const
    ESTADO_ACTIVO = 1,
    ESTADO_BAJA = 2;

    protected $table = 'tercero_contables';

    protected $fillable = [
        'empresa_contable_id', 'tipo_tercero_contable_id', 'sujeto_tipo', 'sujeto_id',
        'nif', 'razon_social', 'cuenta_contable_id', 'estado_id',
    ];

    public function empresaContable()
    {
        return $this->belongsTo(EmpresaContable::class);
    }

    public function tipoTerceroContable()
    {
        return $this->belongsTo(TipoTerceroContable::class);
    }

    public function cuentaContable()
    {
        return $this->belongsTo(CuentaContable::class);
    }

    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }
}

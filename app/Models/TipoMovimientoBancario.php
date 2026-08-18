<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Con qué texto identifica un banco, en sus extractos, un tipo de cargo que nos interesa importar. */
class TipoMovimientoBancario extends Model
{
    protected $table = 'tipos_movimiento_bancario';

    protected $fillable = ['entidad_bancaria_id', 'tipo_operacion', 'prefijo_descripcion', 'codigo'];

    public function entidadBancaria()
    {
        return $this->belongsTo(EntidadBancaria::class);
    }
}

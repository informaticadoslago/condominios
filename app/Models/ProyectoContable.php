<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProyectoContable extends Model
{
    protected $table = 'proyecto_contables';

    protected $fillable = [
        'empresa_contable_id', 'nombre', 'sujeto_tipo', 'sujeto_id',
    ];

    public function empresaContable()
    {
        return $this->belongsTo(EmpresaContable::class);
    }
}

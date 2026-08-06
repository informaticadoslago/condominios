<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoPresupuesto extends Model
{
    const
    CUOTAS = 1,
    DERRAMA = 2;

    protected $table = 'tipo_presupuestos';

    public $timestamps = false;

    protected $fillable = ['descripcion', 'codigo_ingreso'];

    public function presupuestos()
    {
        return $this->hasMany(Presupuesto::class);
    }
}

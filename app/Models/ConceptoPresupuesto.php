<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConceptoPresupuesto extends Model
{
    protected $table = 'conceptos_presupuestos';

    protected $fillable = [
        'presupuesto_id',
        'concepto',
        'importe',
        'grupo_de_reparto_id',
    ];

    public function presupuesto()
    {
        return $this->belongsTo(Presupuesto::class);
    }

    public function grupoDeReparto()
    {
        return $this->belongsTo(GrupoDeReparto::class);
    }
}

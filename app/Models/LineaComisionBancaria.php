<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LineaComisionBancaria extends Model
{
    protected $table = 'lineas_comisiones_bancarias';

    protected $fillable = [
        'comision_bancaria_id',
        'concepto',
        'importe',
    ];

    public function comisionBancaria()
    {
        return $this->belongsTo(ComisionBancaria::class);
    }
}

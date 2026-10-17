<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LineaOrdenDelDia extends Model
{
    protected $table = 'lineas_orden_del_dia';

    protected $fillable = [
        'convocatoria_junta_id',
        'orden',
        'texto',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];

    public function convocatoriaJunta()
    {
        return $this->belongsTo(ConvocatoriaJunta::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un recreo de una jornada: empieza a una hora y dura unos minutos. */
class JornadaRecreo extends Model
{
    protected $table = 'jornada_recreos';

    protected $fillable = [
        'jornada_id',
        'hora_inicio',
        'duracion_minutos',
    ];

    protected $casts = [
        'duracion_minutos' => 'integer',
    ];

    public function jornada()
    {
        return $this->belongsTo(Jornada::class);
    }
}

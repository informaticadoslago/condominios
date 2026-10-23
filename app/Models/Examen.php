<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Examen extends Model
{
    protected $table = 'examenes';

    protected $fillable = [
        'horario_id',
        'asignatura_id',
        'fecha',
        'hora',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    public function asignatura()
    {
        return $this->belongsTo(Asignatura::class);
    }
}

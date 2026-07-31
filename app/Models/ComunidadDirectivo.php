<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComunidadDirectivo extends Model
{
    protected $table = 'comunidad_directivos';

    protected $fillable = [
        'comunidad_id',
        'persona_id',
        'puesto',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    public function comunidad()
    {
        return $this->belongsTo(Comunidad::class);
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }
}

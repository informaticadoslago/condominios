<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Representante extends Model
{
    protected $table = 'representantes';

    protected $fillable = [
        'persona_id',
        'representante_id',
        'fecha_inicio',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
    ];

    /** La persona representada (jurídica o menor). */
    public function persona()
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    /** La persona física que representa. */
    public function representante()
    {
        return $this->belongsTo(Persona::class, 'representante_id');
    }
}

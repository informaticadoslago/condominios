<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComunidadDirectivo extends Model
{
    protected $table = 'comunidad_directivos';

    protected $fillable = [
        'comunidad_id',
        'persona_comunidad_id',
        'tipo_directivo_comunidad_id',
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
        return $this->belongsTo(PersonaComunidad::class, 'persona_comunidad_id');
    }

    public function tipoDirectivo()
    {
        return $this->belongsTo(TipoDirectivoComunidad::class, 'tipo_directivo_comunidad_id');
    }
}

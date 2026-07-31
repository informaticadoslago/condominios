<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrupoDeReparto extends Model
{
    protected $table = 'grupos_de_reparto';

    protected $fillable = [
        'comunidad_id',
        'nombre',
    ];

    public function comunidad()
    {
        return $this->belongsTo(Comunidad::class);
    }

    public function inmuebles()
    {
        return $this->belongsToMany(Inmueble::class, 'inmueble_grupo_de_reparto', 'grupo_de_reparto_id', 'inmueble_id');
    }
}

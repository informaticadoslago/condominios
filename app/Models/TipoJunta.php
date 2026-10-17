<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoJunta extends Model
{
    const
    ORDINARIA = 1,
    EXTRAORDINARIA = 2;

    protected $table = 'tipo_juntas';

    public $timestamps = false;

    protected $fillable = ['descripcion'];

    public function convocatorias()
    {
        return $this->hasMany(ConvocatoriaJunta::class);
    }
}

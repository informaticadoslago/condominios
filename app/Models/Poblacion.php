<?php

namespace App\Models;


use App\Models\Traits\Ordenable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Poblacion extends Model
{
    use HasFactory, Ordenable;

    public $timestamps=false;

    protected $table='poblaciones';

    public function municipio()
    {
        return $this->belongsTo(Municipio::class);        
    }

    public function scopeDeMunicipio(Builder $query, $municipio_id) : void
    {
        $query->where('municipio_id', $municipio_id);
    }

}

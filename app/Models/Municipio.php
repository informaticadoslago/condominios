<?php

namespace App\Models;

use App\Models\Traits\Ordenable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Municipio extends Model
{
    use HasFactory, Ordenable;

    public $timestamps = false;

    protected $fillable = [
        'provincia_id', 'codigo', 'nombre',
    ];

    public function provincia()
    {
        return $this->belongsTo(Provincia::class);
    }

    public function poblaciones()
    {
        return $this->hasMany(Poblacion::class);
    }

    public function codigos_postales()
    {
        return $this->hasMany(CodigoPostal::class);
    }

    public function vias()
    {
        return $this->hasMany(Via::class);
    }

    public function scopeDeProvincia(Builder $query, $provincia_id): void
    {
        $query->where('provincia_id', $provincia_id);
    }

}

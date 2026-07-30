<?php

namespace App\Models;

use App\Models\Traits\Ordenable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Provincia extends Model
{
    use HasFactory, Ordenable;

    public $timestamps = false;

    public function pais()
    {
        return $this->belongsTo(Pais::class);
    }

    public function municipios()
    {
        return $this->hasMany(Municipio::class);
    }

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantillaFactura extends Model
{
    protected $table = 'plantillas_facturas';

    protected $fillable = [
        'cif',
        'razon_social',
    ];

    public function campos()
    {
        return $this->hasMany(CampoPlantillaFactura::class);
    }
}

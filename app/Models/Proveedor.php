<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Proveedor extends Model
{
    protected $table = 'proveedores';

    protected $fillable = [
        'persona_id',
    ];

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    public function cuentasBancarias(): MorphMany
    {
        return $this->morphMany(CuentaBancaria::class, 'titular');
    }
}

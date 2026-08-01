<?php

namespace App\Models;

use App\Models\Traits\ConDocumentos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Proveedor extends Model
{
    use ConDocumentos;

    protected $table = 'proveedores';

    protected $fillable = [
        'persona_comunidad_id',
    ];

    public function persona()
    {
        return $this->belongsTo(PersonaComunidad::class, 'persona_comunidad_id');
    }

    public function cuentasBancarias(): MorphMany
    {
        return $this->morphMany(CuentaBancaria::class, 'titular');
    }
}

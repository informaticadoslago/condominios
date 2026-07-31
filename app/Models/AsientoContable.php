<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsientoContable extends Model
{
    protected $table = 'asiento_contables';

    protected $fillable = [
        'ejercicio_contable_id', 'numero', 'fecha', 'concepto',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function ejercicioContable()
    {
        return $this->belongsTo(EjercicioContable::class);
    }

    public function apuntesContables()
    {
        return $this->hasMany(ApunteContable::class);
    }
}

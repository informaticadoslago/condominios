<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EjercicioContable extends Model
{
    protected $table = 'ejercicio_contables';

    protected $fillable = [
        'empresa_contable_id', 'nombre', 'fecha_inicio', 'fecha_fin', 'cerrado',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'cerrado' => 'boolean',
    ];

    public function empresaContable()
    {
        return $this->belongsTo(EmpresaContable::class);
    }

    public function asientosContables()
    {
        return $this->hasMany(AsientoContable::class);
    }
}

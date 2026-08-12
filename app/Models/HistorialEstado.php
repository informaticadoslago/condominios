<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistorialEstado extends Model
{
    protected $table = 'historial_estados';

    const UPDATED_AT = null; // solo interesa cuándo se registró el cambio

    protected $fillable = [
        'estado_anterior',
        'estado_nuevo',
        'user_id',
        'motivo',
        'fecha',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function estadoable()
    {
        return $this->morphTo();
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

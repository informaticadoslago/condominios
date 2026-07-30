<?php

namespace App\Models\Traits;

trait Ordenable
{
    public function scopeOrdenaPorOrden($query) {
        return $query->orderByDesc('orden');
    }
    
    public function scopeOrdenaPorNombre($query) {
        return $query->orderBy('nombre');
    }

    public function scopeOrdenaPorDescripcion($query) {
        return $query->orderBy('descripcion');
    }
}

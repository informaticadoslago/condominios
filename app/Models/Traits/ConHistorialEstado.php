<?php

namespace App\Models\Traits;

use App\Models\HistorialEstado;

/**
 * Registra en historial_estados cada cambio de estado_id del modelo:
 * el estado inicial al crear y cada transición posterior (altas/bajas).
 * Solo registra cambios hechos desde L12 (eventos Eloquent).
 */
trait ConHistorialEstado
{
    public static function bootConHistorialEstado()
    {
        static::created(function ($model) {
            if (! is_null($model->getAttributes()['estado_id'] ?? null)) {
                $model->registrarCambioEstado(null, $model->estado_id);
            }
        });

        static::updated(function ($model) {
            if ($model->wasChanged('estado_id')) {
                $model->registrarCambioEstado($model->getOriginal('estado_id'), $model->estado_id);
            }
        });
    }

    public function historialEstados()
    {
        return $this->morphMany(HistorialEstado::class, 'estadoable');
    }

    /**
     * Último cambio de estado registrado: cuándo (created_at), quién (usuario)
     * y por qué (motivo). Eager-loadable: with('ultimoCambioEstado').
     */
    public function ultimoCambioEstado()
    {
        return $this->morphOne(HistorialEstado::class, 'estadoable')->latestOfMany();
    }

    public function registrarCambioEstado($anterior, $nuevo, ?string $motivo = null): void
    {
        $this->historialEstados()->create([
            'estado_anterior' => $anterior,
            'estado_nuevo'    => $nuevo,
            'user_id'         => auth()->id(),
            'motivo'          => $motivo,
        ]);
    }
}

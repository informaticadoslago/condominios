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
    /**
     * Por qué cambia el estado esta vez, para que quede en el historial: «Remesa
     * REM1-20260809-1», «Devuelto por el banco»… Se pone justo antes de guardar y se
     * consume al registrarlo, así que no se arrastra al cambio siguiente.
     */
    public ?string $motivoCambioEstado = null;

    /**
     * Fecha de negocio del cambio, cuando es distinta de hoy: la fecha de cobro de un
     * recibo, por ejemplo. Igual que $motivoCambioEstado, se pone justo antes de guardar
     * y se consume al registrarlo. Si no se indica, se registra con fecha de hoy (fecha
     * de registro): cada pantalla puede empezar a pedirla y rellenar este campo sin
     * necesitar ninguna migración nueva, la columna ya existe para todos los modelos.
     */
    public ?string $fechaCambioEstado = null;

    public static function bootConHistorialEstado()
    {
        static::created(function ($model) {
            if (! is_null($model->getAttributes()['estado_id'] ?? null)) {
                $model->registrarCambioEstado(null, $model->estado_id, $model->motivoCambioEstado, $model->fechaCambioEstado);
                $model->motivoCambioEstado = null;
                $model->fechaCambioEstado = null;
            }
        });

        static::updated(function ($model) {
            if ($model->wasChanged('estado_id')) {
                $model->registrarCambioEstado($model->getOriginal('estado_id'), $model->estado_id, $model->motivoCambioEstado, $model->fechaCambioEstado);
                $model->motivoCambioEstado = null;
                $model->fechaCambioEstado = null;
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

    public function registrarCambioEstado($anterior, $nuevo, ?string $motivo = null, ?string $fecha = null): void
    {
        $this->historialEstados()->create([
            'estado_anterior' => $anterior,
            'estado_nuevo'    => $nuevo,
            'user_id'         => auth()->id(),
            'motivo'          => $motivo,
            'fecha'           => $fecha ?? now()->toDateString(),
        ]);
    }
}

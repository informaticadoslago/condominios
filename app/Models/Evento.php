<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Un evento del calendario de un horario: un examen, una junta de evaluación, un
 * claustro… (ver TipoEvento). Solo el examen pide asignatura; en el resto es opcional.
 */
class Evento extends Model
{
    /** La alarma que propone el formulario para un evento nuevo. */
    const ALARMA_POR_DEFECTO = 1440;

    /** Filtro "Mostrar" de la lista (y su impresión): los de hoy en adelante, o todos. */
    const
        PERIODO_FUTUROS = 'futuros',
        PERIODO_TODOS = 'todos';

    protected $table = 'eventos';

    protected $fillable = [
        'horario_id',
        'tipo_evento_id',
        'asignatura_id',
        'titulo',
        'fecha',
        'hora',
        'hora_fin',
        'alarma_minutos',
    ];

    protected $casts = [
        'fecha' => 'date',
        'tipo_evento_id' => 'integer',
        'alarma_minutos' => 'integer',
    ];

    /** [minutos antes del inicio => texto] para el desplegable; "sin alarma" (null) va aparte. */
    public static function opcionesAlarma(): array
    {
        return [
            15    => __('15 minutos antes'),
            30    => __('30 minutos antes'),
            60    => __('1 hora antes'),
            120   => __('2 horas antes'),
            1440  => __('1 día antes'),
            2880  => __('2 días antes'),
            10080 => __('1 semana antes'),
        ];
    }

    /**
     * Los eventos del horario en orden y agrupados por mes: ['Noviembre 2026' =>
     * Collection<Evento>, …]. Es lo que sale al imprimir: debajo del horario semanal (de
     * fábrica, los que quedan, los de hoy incluidos) y en el PDF de la lista de eventos
     * (con los filtros que tenga puestos la lista).
     */
    public static function porMes(int $horarioId, bool $soloFuturos = true, ?int $asignaturaId = null, ?int $tipoEventoId = null): Collection
    {
        return self::with(['asignatura.modulo', 'tipoEvento'])
            ->where('horario_id', $horarioId)
            ->when($soloFuturos, fn ($q) => $q->where('fecha', '>=', today()->toDateString()))
            ->when($asignaturaId, fn ($q) => $q->where('asignatura_id', $asignaturaId))
            ->when($tipoEventoId, fn ($q) => $q->where('tipo_evento_id', $tipoEventoId))
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get()
            ->groupBy(fn (self $evento) => ucfirst($evento->fecha->isoFormat('MMMM YYYY')));
    }

    public function esExamen(): bool
    {
        return $this->tipo_evento_id === TipoEvento::EXAMEN;
    }

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    public function tipoEvento()
    {
        return $this->belongsTo(TipoEvento::class);
    }

    public function asignatura()
    {
        return $this->belongsTo(Asignatura::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Examen extends Model
{
    /** La alarma que propone el formulario para un examen nuevo. */
    const ALARMA_POR_DEFECTO = 1440;

    protected $table = 'examenes';

    protected $fillable = [
        'horario_id',
        'asignatura_id',
        'titulo',
        'fecha',
        'hora',
        'hora_fin',
        'alarma_minutos',
    ];

    protected $casts = [
        'fecha' => 'date',
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

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    public function asignatura()
    {
        return $this->belongsTo(Asignatura::class);
    }
}

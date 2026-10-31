<?php

namespace App\Models;

use App\Services\Horarios\ColocacionSesiones;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Un aviso push que quiere un usuario en una sesión de un horario: al empezar o al acabar
 * ($momento), con sus minutos de antelación. Se ponen con la campana de Editar del horario
 * semanal y los manda la orden condominios:horarios-avisos, que se lanza cada minuto y
 * solo busca por dia_semana y hora_aviso: el minuto en que salta va ya calculado (ver
 * recalcular()).
 */
class HorarioSesionAviso extends Model
{
    const
        MOMENTO_INICIO = 'inicio',
        MOMENTO_FIN = 'fin';

    protected $table = 'horario_sesion_avisos';

    protected $fillable = [
        'user_id',
        'horario_id',
        'horario_sesion_id',
        'momento',
        'minutos_antes',
        'dia_semana',
        'hora_aviso',
    ];

    protected $casts = [
        'minutos_antes' => 'integer',
        'dia_semana' => 'integer',
    ];

    /** [minutos antes => texto] para los dos desplegables; "ninguno" (sin aviso) va aparte. */
    public static function opciones(): array
    {
        return [
            0  => __('A la hora'),
            5  => __('5 minutos antes'),
            10 => __('10 minutos antes'),
            15 => __('15 minutos antes'),
            30 => __('30 minutos antes'),
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    public function sesion()
    {
        return $this->belongsTo(HorarioSesion::class, 'horario_sesion_id');
    }

    /** 'H:i' a la que empieza o acaba la sesión: la del aviso más la antelación. */
    public function horaSesion(): string
    {
        return Carbon::createFromFormat('H:i:s', $this->hora_aviso)->addMinutes($this->minutos_antes)->format('H:i');
    }

    /**
     * Calcula otra vez hora_aviso en todos los avisos del horario, con las sesiones donde
     * caen ahora (ColocacionSesiones, contando las movidas a mano). Hay que llamarla
     * siempre que algo pueda mover una sesión: al guardar un aviso, la rejilla, al
     * arrastrar una sesión y los días y sesiones. Una sesión que ya no cae en ninguna
     * jornada deja sus avisos sin hora: no saltan.
     */
    public static function recalcular(int $horarioId): void
    {
        $avisos = self::where('horario_id', $horarioId)->get();

        if ($avisos->isEmpty()) {
            return;
        }

        $horario = Horario::with(['jornadas.recreos', 'sesiones'])->find($horarioId);
        $colocacion = new ColocacionSesiones($horario);
        $ids = $horario->sesiones->mapWithKeys(fn ($s) => [$s->dia_semana.'-'.$s->sesion_numero => $s->id]);

        $horas = [];
        foreach ($colocacion->dias() as $dia) {
            foreach ($colocacion->sesiones($dia) as $sesion) {
                $horas[$ids[$dia.'-'.$sesion['numero']] ?? 0] = $sesion;
            }
        }

        foreach ($avisos as $aviso) {
            $sesion = $horas[$aviso->horario_sesion_id] ?? null;
            $minutos = $sesion ? $sesion[$aviso->momento] - $aviso->minutos_antes : null;

            $aviso->hora_aviso = $minutos !== null && $minutos >= 0 ? ColocacionSesiones::hora($minutos).':00' : null;
            $aviso->save();
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Models\HorarioAvisoDispositivo;
use App\Models\HorarioSesionAviso;
use App\Notifications\AvisoSesion;
use Illuminate\Console\Command;

/**
 * Los avisos push de inicio y fin de sesión que cada usuario pone con la campana de Editar
 * en el horario semanal. La lanza el programador cada minuto (routes/console.php) y manda
 * los de hoy cuya hora_aviso es este minuto: la hora va ya calculada en cada aviso (ver
 * HorarioSesionAviso::recalcular()), aquí no se mira ninguna jornada.
 *
 * No se manda nada a quien tenga las notificaciones desactivadas, no esté activo o ya no
 * tenga acceso al horario.
 */
class HorariosAvisos extends Command
{
    protected $signature = 'condominios:horarios-avisos';

    protected $description = 'Manda los avisos push de inicio y fin de sesión de los horarios que tocan en este minuto.';

    public function handle(): int
    {
        $ahora = now();
        $enviados = 0;

        $avisos = HorarioSesionAviso::with(['user', 'sesion.asignatura'])
            ->where('dia_semana', $ahora->dayOfWeekIso)
            ->where('hora_aviso', $ahora->format('H:i:00'))
            ->whereHas('user', fn ($q) => $q->activo()->where('notificaciones_push', true))
            ->get();

        // Los dispositivos elegidos por cada usuario en cada horario; sin ninguno, van a todos.
        $elegidos = $avisos->isEmpty() ? collect() : HorarioAvisoDispositivo::whereIn('user_id', $avisos->pluck('user_id')->unique())
            ->whereIn('horario_id', $avisos->pluck('horario_id')->unique())
            ->get()
            ->groupBy(fn ($elegido) => $elegido->user_id.'-'.$elegido->horario_id);

        foreach ($avisos as $aviso) {
            if (! $aviso->user->horariosAccesibles()->contains('id', $aviso->horario_id)) {
                continue;
            }

            $dispositivos = $elegidos->get($aviso->user_id.'-'.$aviso->horario_id)?->pluck('push_subscription_id')->all();

            $aviso->user->notify(new AvisoSesion($aviso, $dispositivos));
            $enviados++;
        }

        $this->line("Avisos enviados: {$enviados}");

        return self::SUCCESS;
    }
}

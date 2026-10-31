<?php

namespace App\Console\Commands;

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

        foreach ($avisos as $aviso) {
            if (! $aviso->user->horariosAccesibles()->contains('id', $aviso->horario_id)) {
                continue;
            }

            $aviso->user->notify(new AvisoSesion($aviso));
            $enviados++;
        }

        $this->line("Avisos enviados: {$enviados}");

        return self::SUCCESS;
    }
}

<?php

namespace App\Notifications;

use App\Models\HorarioSesionAviso;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Aviso push de que empieza o acaba una sesión del horario (ver HorariosAvisos), a todos
 * los dispositivos del usuario. Al pulsarlo se entra en ese horario.
 */
class AvisoSesion extends Notification
{
    /** Con la relación 'sesion.asignatura' cargada. */
    public function __construct(public HorarioSesionAviso $aviso)
    {
    }

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $asignatura = $this->aviso->sesion->asignatura;
        $inicio = $this->aviso->momento === HorarioSesionAviso::MOMENTO_INICIO;
        $ahora = $this->aviso->minutos_antes === 0;
        $datos = ['hora' => $this->aviso->horaSesion(), 'minutos' => $this->aviso->minutos_antes];

        $texto = match (true) {
            $inicio && $ahora => __('Empieza ahora, a las :hora.', $datos),
            $inicio => __('Empieza dentro de :minutos minutos, a las :hora.', $datos),
            $ahora => __('Acaba ahora, a las :hora.', $datos),
            default => __('Acaba dentro de :minutos minutos, a las :hora.', $datos),
        };

        if ($inicio && $asignatura->aula) {
            $texto .= ' '.__('Aula: :aula.', ['aula' => $asignatura->aula]);
        }

        return (new WebPushMessage)
            ->title($asignatura->nombre)
            ->body($texto)
            ->icon(asset('images/logo/icono-app.png'))
            ->data(['url' => route('horario.entrar', $this->aviso->horario_id)]);
    }
}

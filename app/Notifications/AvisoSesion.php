<?php

namespace App\Notifications;

use App\Models\HorarioSesionAviso;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Aviso push de que empieza o acaba una sesión del horario (ver HorariosAvisos). Al
 * pulsarlo se entra en ese horario.
 */
class AvisoSesion extends Notification
{
    /**
     * @param  HorarioSesionAviso  $aviso  con la relación 'sesion.asignatura' cargada
     * @param  array<int>|null  $dispositivos  ids de los dispositivos elegidos para los avisos
     *         de ese horario (HorarioAvisoDispositivo), o null para todos. Lo lee
     *         User::routeNotificationForWebPush().
     */
    public function __construct(public HorarioSesionAviso $aviso, public ?array $dispositivos = null)
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

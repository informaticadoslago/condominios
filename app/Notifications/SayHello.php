<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notificación push de prueba ("Say Hello" de Pruebas de correo): llega a todos los
 * navegadores en los que el usuario tiene activadas las notificaciones.
 */
class SayHello extends Notification
{
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title(config('app.name'))
            ->body(__('Hola, :nombre. Las notificaciones funcionan.', ['nombre' => $notifiable->login]))
            ->icon(asset('images/logo/icono-app.png'))
            ->data(['url' => route('dashboard')]);
    }
}

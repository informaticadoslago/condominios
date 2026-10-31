<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\PushSubscription;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notificación push de prueba ("Say Hello" de Pruebas de correo y "Probar" de los
 * dispositivos del perfil): a un dispositivo o, sin él, a todos los del usuario.
 */
class SayHello extends Notification
{
    /** Lo lee User::routeNotificationForWebPush(). */
    public function __construct(public ?PushSubscription $dispositivo = null)
    {
    }

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

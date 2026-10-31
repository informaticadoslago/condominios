<?php
namespace App\Livewire;

use App\Support\NombreDispositivo;
use Livewire\Component;
use NotificationChannels\WebPush\PushSubscription;

/**
 * Aviso del layout "¿Activar notificaciones en este navegador?" y alta de la suscripción
 * de cada navegador. Solo se pregunta si el usuario tiene el sí general de su perfil
 * (users.notificaciones_push); lo que pasa en el navegador está en
 * resources/js/notificaciones-push.js.
 */
class NotificacionesPush extends Component
{
    public bool $activas = false;

    /** Las suscripciones ya guardadas del usuario: si la de este navegador está, no se reenvía en cada página. */
    public array $endpoints = [];

    public function mount(): void
    {
        $this->cargar();
    }

    /** También la llama el aviso cuando cambian en el perfil (evento notificaciones-push-cambiadas). */
    public function cargar(): void
    {
        $usuario = auth()->user();

        $this->activas   = $usuario->notificaciones_push;
        $this->endpoints = $usuario->pushSubscriptions()->pluck('endpoint')->all();
    }

    public function guardar(array $suscripcion): void
    {
        $usuario = auth()->user();

        if (! $usuario->notificaciones_push) {
            return;
        }

        $datos = validator($suscripcion, [
            'endpoint'        => ['required', 'string', 'starts_with:https://', 'max:' . PushSubscription::ENDPOINT_MAX_LENGTH],
            'keys.p256dh'     => ['required', 'string', 'max:255'],
            'keys.auth'       => ['required', 'string', 'max:255'],
            'contentEncoding' => ['required', 'in:aesgcm,aes128gcm'],
        ])->validate();

        $suscripcion = $usuario->updatePushSubscription($datos['endpoint'], $datos['keys']['p256dh'], $datos['keys']['auth'], $datos['contentEncoding']);

        if (! $suscripcion->nombre) {
            $suscripcion->nombre = NombreDispositivo::desde(request()->userAgent());
            $suscripcion->save();
        }

        $this->endpoints[] = $datos['endpoint'];
    }

    public function render()
    {
        return view('livewire.notificaciones-push');
    }
}

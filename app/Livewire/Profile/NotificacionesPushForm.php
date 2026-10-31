<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Sí/no general a las notificaciones push y estado de este navegador. Con "no" se borran
 * las suscripciones de todos sus navegadores: el permiso de cada navegador no se puede
 * quitar desde aquí, pero sin suscripción no le llega nada. Con "sí" otra vez, los
 * navegadores que conserven el permiso se vuelven a suscribir solos al entrar.
 */
class NotificacionesPushForm extends Component
{
    public bool $activas = false;

    public function mount(): void
    {
        $this->activas = Auth::user()->notificaciones_push;
    }

    public function updatedActivas(): void
    {
        $usuario = Auth::user();

        $usuario->notificaciones_push = $this->activas;
        $usuario->save();

        if (! $this->activas) {
            $usuario->pushSubscriptions()->delete();
        }

        // Lo escucha el aviso del layout (livewire/notificaciones-push).
        $this->dispatch('notificaciones-push-cambiadas');
    }

    /** "Desactivar en este navegador": el navegador ya ha anulado su suscripción. */
    public function quitar(string $endpoint): void
    {
        Auth::user()->deletePushSubscription($endpoint);

        $this->dispatch('notificaciones-push-cambiadas');
    }

    public function render()
    {
        return view('profile.notificaciones-push-form');
    }
}

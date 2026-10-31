<?php

namespace App\Livewire\Profile;

use App\Notifications\SayHello;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Sí/no general a las notificaciones push, estado de este navegador y, en "Configurar",
 * los dispositivos del usuario (uno por navegador suscrito): nombre, probar y quitar.
 *
 * Con "no" se borran las suscripciones de todos sus navegadores: el permiso de cada
 * navegador no se puede quitar desde aquí, pero sin suscripción no le llega nada. Con
 * "sí" otra vez, cada navegador vuelve a preguntar (el aviso del layout).
 */
class NotificacionesPushForm extends Component
{
    public bool $activas = false;

    public bool $abrirConfigurar = false;

    /** Las suscripciones guardadas, para saber en la vista si la de este navegador está. */
    public array $endpoints = [];

    /** Nombre de cada dispositivo por id de suscripción: se guarda al salir del campo. */
    public array $nombres = [];

    public function mount(): void
    {
        $this->activas = Auth::user()->notificaciones_push;
        $this->cargar();
    }

    #[Computed]
    public function dispositivos()
    {
        return Auth::user()->pushSubscriptions()->orderBy('created_at')->get();
    }

    /** También la llama la vista cuando el aviso del layout da de alta este navegador. */
    public function cargar(): void
    {
        unset($this->dispositivos);

        $this->endpoints = $this->dispositivos->pluck('endpoint')->all();
        $this->nombres   = $this->dispositivos->pluck('nombre', 'id')->all();
    }

    public function updatedActivas(): void
    {
        $usuario = Auth::user();

        $usuario->notificaciones_push = $this->activas;
        $usuario->save();

        if (! $this->activas) {
            $usuario->pushSubscriptions()->delete();
            $this->abrirConfigurar = false;
        }

        $this->cargar();

        // Lo escucha el aviso del layout (livewire/notificaciones-push).
        $this->dispatch('notificaciones-push-cambiadas');
    }

    public function configurar(): void
    {
        $this->resetErrorBag();
        $this->cargar();
        $this->abrirConfigurar = true;
    }

    public function close(): void
    {
        $this->abrirConfigurar = false;
    }

    public function updatedNombres($nombre, $id): void
    {
        $this->nombres[$id] = trim((string) $nombre);

        $this->validate(
            ["nombres.{$id}" => ['required', 'string', 'max:60']],
            [],
            ["nombres.{$id}" => __('nombre')]
        );

        Auth::user()->pushSubscriptions()->whereKey($id)->update(['nombre' => $this->nombres[$id]]);
    }

    public function probar(int $id): void
    {
        $dispositivo = Auth::user()->pushSubscriptions()->findOrFail($id);

        Auth::user()->notify(new SayHello($dispositivo));

        $this->dispatch('toast-success', ['title' => __('Notificación enviada a :nombre', ['nombre' => $dispositivo->nombre])]);
    }

    public function probarTodos(): void
    {
        Auth::user()->notify(new SayHello());

        $this->dispatch('toast-success', ['title' => __('Notificación enviada a :n dispositivo(s)', ['n' => $this->dispositivos->count()])]);
    }

    /** Quita un dispositivo de otro navegador: si se vuelve a entrar desde él, se le pregunta otra vez. */
    public function quitar(int $id): void
    {
        Auth::user()->pushSubscriptions()->whereKey($id)->delete();

        $this->cargar();
        $this->dispatch('notificaciones-push-cambiadas');
    }

    /** "Desactivar en este navegador" (o quitarlo de la lista): el navegador ya ha anulado su suscripción. */
    public function quitarEndpoint(string $endpoint): void
    {
        Auth::user()->deletePushSubscription($endpoint);

        $this->cargar();
        $this->dispatch('notificaciones-push-cambiadas');
    }

    public function render()
    {
        return view('profile.notificaciones-push-form');
    }
}

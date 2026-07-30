<?php

namespace App\Livewire\Traits;

use App\Models\AccesoDirecto;
use Livewire\Attributes\Computed;

/**
 * Añade "Crear acceso directo" a un componente de página (Lista).
 * El acceso apunta a la entrada de menú de la pantalla actual; si no está en el menú, no se ofrece.
 */
trait ConAccesoDirecto
{
    public string $rutaActual = '';

    // Hook de Livewire: se ejecuta en el mount aunque el componente tenga su propio mount().
    public function mountConAccesoDirecto(): void
    {
        $this->rutaActual = request()->path();
    }

    #[Computed]
    public function entradaMenu(): ?array
    {
        return AccesoDirecto::entradaMenuPara($this->rutaActual);
    }

    #[Computed]
    public function accesoDirectoGuardado(): bool
    {
        $entrada = $this->entradaMenu();
        if (! $entrada) {
            return false;
        }

        return AccesoDirecto::where('user_id', auth()->id())
            ->where('url', $entrada['url'])
            ->exists();
    }

    public function crearAccesoDirecto(): void
    {
        $entrada = $this->entradaMenu();
        if (! $entrada) {
            $this->dispatch('toast-error', ['title' => __('Esta pantalla no está en el menú')]);
            return;
        }

        $userId = auth()->id();

        if (AccesoDirecto::where('user_id', $userId)->where('url', $entrada['url'])->exists()) {
            $this->dispatch('toast-error', ['title' => __('Ya tienes este acceso directo')]);
            return;
        }

        $orden = (AccesoDirecto::where('user_id', $userId)->max('orden') ?? 0) + 1;

        AccesoDirecto::create([
            'user_id' => $userId,
            'nombre'  => $entrada['nombre'],
            'url'     => $entrada['url'],
            'icono'   => $entrada['icono'],
            'orden'   => $orden,
        ]);

        unset($this->accesoDirectoGuardado);
        $this->dispatch('toast-success', ['title' => __('Acceso directo creado')]);
    }
}

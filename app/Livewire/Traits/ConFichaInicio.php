<?php

namespace App\Livewire\Traits;

use App\Models\AccesoDirecto;
use Livewire\Attributes\Computed;

/**
 * Añade "Ver al inicio" a las líneas de un listado: deja en el dashboard una ficha
 * que entra directamente en esa comunidad o empresa contable.
 *
 * Es el mismo mecanismo que ConAccesoDirecto (una fila en accesos_directos), pero
 * por línea y no por pantalla. El listado que lo use tiene que implementar
 * fichaInicioPara(), que decide si esa línea es fijable y con qué nombre e icono.
 */
trait ConFichaInicio
{
    /**
     * Datos de la ficha de una línea, o null si el usuario no tiene acceso a ella:
     * ['tipo' => ..., 'nombre' => ..., 'url' => ..., 'icono' => ...].
     */
    abstract protected function fichaInicioPara($id): ?array;

    /** Urls ya fijadas en el inicio, para pintar el botón como hecho. */
    #[Computed]
    public function urlsEnInicio(): array
    {
        return AccesoDirecto::where('user_id', auth()->id())
            ->fichas()
            ->pluck('url')
            ->all();
    }

    public function estaEnInicio(string $url): bool
    {
        return in_array($url, $this->urlsEnInicio(), true);
    }

    public function fijarEnInicio($id): void
    {
        $ficha = $this->fichaInicioPara($id);

        if (! $ficha) {
            $this->dispatch('toast-error', ['title' => __('No tienes acceso a esa ficha')]);

            return;
        }

        $userId = auth()->id();

        if (AccesoDirecto::where('user_id', $userId)->where('url', $ficha['url'])->exists()) {
            $this->dispatch('toast-error', ['title' => __('Ya la tienes en el inicio')]);

            return;
        }

        $orden = (AccesoDirecto::where('user_id', $userId)->max('orden') ?? 0) + 1;

        AccesoDirecto::create($ficha + [
            'user_id' => $userId,
            'orden'   => $orden,
        ]);

        unset($this->urlsEnInicio);
        $this->dispatch('toast-success', ['title' => __('Añadida al inicio')]);
    }
}

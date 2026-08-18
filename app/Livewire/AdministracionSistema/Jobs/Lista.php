<?php

namespace App\Livewire\AdministracionSistema\Jobs;

use Carbon\Carbon;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Ver qué hay en las colas: una pestaña por cola (jobs pendientes y fallidos comparten
 * el mismo nombre de cola, así que agrupan igual), con lo pendiente y lo fallido de esa
 * cola en dos listas, una encima de otra.
 *
 * Lo pendiente solo se puede borrar (aún no ha corrido, no hay nada que reintentar). Lo
 * fallido se puede reintentar (queue:retry, lo reencola) o borrar sin más.
 */
class Lista extends Component
{
    public string $colaActiva = '';

    /** Ids de jobs pendientes marcados para borrar. */
    public array $seleccionPendientes = [];

    public function mount(): void
    {
        $this->colaActiva = $this->colas()->first() ?? '';
    }

    /** Casilla de la cabecera: marca todos los pendientes de la cola, o los desmarca si ya estaban todos. */
    public function toggleTodosPendientes(array $ids): void
    {
        $ids = array_map('strval', $ids);
        sort($ids);

        $actuales = $this->seleccionPendientes;
        sort($actuales);

        $this->seleccionPendientes = $actuales === $ids ? [] : $ids;
    }

    public function cambiarCola(string $cola): void
    {
        $this->colaActiva          = $cola;
        $this->seleccionPendientes = [];
    }

    public function borrarPendientesSeleccionados(): void
    {
        if ($this->seleccionPendientes === []) {
            $this->dispatch('toast-error', ['title' => __('No hay ninguno marcado')]);

            return;
        }

        $borrados = DB::table('jobs')
            ->where('queue', $this->colaActiva)
            ->whereIn('id', array_map('intval', $this->seleccionPendientes))
            ->delete();

        $this->seleccionPendientes = [];

        $this->dispatch('toast-success', [
            'title' => __(':count trabajos pendientes borrados', ['count' => $borrados]),
        ]);
    }

    /**
     * No hay "pausar" de verdad en la cola database (eso es cosa de Horizon, que aquí no
     * se usa): lo que se puede hacer es empujar available_at a un futuro lejano, para
     * que el worker nunca lo vea disponible sin borrar la fila. Reanudar es lo
     * contrario: lo pone disponible ahora mismo.
     */
    public function pausarPendientesSeleccionados(): void
    {
        if ($this->seleccionPendientes === []) {
            $this->dispatch('toast-error', ['title' => __('No hay ninguno marcado')]);

            return;
        }

        DB::table('jobs')
            ->where('queue', $this->colaActiva)
            ->whereIn('id', array_map('intval', $this->seleccionPendientes))
            ->update(['available_at' => now()->addYears(10)->timestamp]);

        $this->seleccionPendientes = [];

        $this->dispatch('toast-success', ['title' => __('Trabajos pausados')]);
    }

    public function reanudarPendiente(int $id): void
    {
        DB::table('jobs')
            ->where('queue', $this->colaActiva)
            ->where('id', $id)
            ->update(['available_at' => now()->timestamp]);

        $this->dispatch('toast-success', ['title' => __('Trabajo reanudado')]);
    }

    public function reintentarFallido(string $uuid): void
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);

        $this->dispatch('toast-success', ['title' => __('Trabajo reencolado')]);
    }

    public function borrarFallido(int $id): void
    {
        DB::table('failed_jobs')->where('id', $id)->delete();

        $this->dispatch('toast-success', ['title' => __('Trabajo fallido borrado')]);
    }

    /** Nombres de cola que hay hoy, entre pendientes y fallidos, sin repetir. */
    private function colas()
    {
        return DB::table('jobs')->select('queue')
            ->union(DB::table('failed_jobs')->select('queue'))
            ->distinct()
            ->pluck('queue')
            ->filter()
            ->sort()
            ->values();
    }

    /**
     * Qué job es de verdad. Un correo encolado (Mail::queue) siempre da el mismo
     * displayName —Illuminate\Mail\SendQueuedMailable—, así que para saber cuál
     * mailable es de verdad hay que mirar dentro del payload serializado.
     */
    private function nombreJob(string $payload): string
    {
        $datos       = json_decode($payload, true);
        $displayName = $datos['displayName'] ?? '?';

        if ($displayName === SendQueuedMailable::class
            && preg_match('/"mailable";O:\d+:"([^"]+)"/', $payload, $m)) {
            return class_basename($m[1]);
        }

        return class_basename($displayName);
    }

    public function render()
    {
        $colas = $this->colas();

        if ($this->colaActiva === '' || ! $colas->contains($this->colaActiva)) {
            $this->colaActiva          = $colas->first() ?? '';
            $this->seleccionPendientes = [];
        }

        $pendientes = DB::table('jobs')
            ->where('queue', $this->colaActiva)
            ->orderBy('id')
            ->get()
            ->map(fn ($j) => [
                'id'           => $j->id,
                'job'          => $this->nombreJob($j->payload),
                'attempts'     => $j->attempts,
                'created_at'   => Carbon::createFromTimestamp($j->created_at, config('app.timezone')),
                'available_at' => Carbon::createFromTimestamp($j->available_at, config('app.timezone')),
                // Un retraso normal es de segundos o minutos; más de un día de margen
                // solo lo pone pausarPendientesSeleccionados().
                'pausado' => $j->available_at > now()->addDay()->timestamp,
            ]);

        $fallidos = DB::table('failed_jobs')
            ->where('queue', $this->colaActiva)
            ->orderByDesc('failed_at')
            ->get()
            ->map(fn ($j) => [
                'id'        => $j->id,
                'uuid'      => $j->uuid,
                'job'       => $this->nombreJob($j->payload),
                'exception' => Str::limit(strtok($j->exception, "\n"), 200),
                'failed_at' => $j->failed_at,
            ]);

        return view('livewire.administracion-sistema.jobs.lista', [
            'colas'      => $colas,
            'pendientes' => $pendientes,
            'fallidos'   => $fallidos,
        ]);
    }
}

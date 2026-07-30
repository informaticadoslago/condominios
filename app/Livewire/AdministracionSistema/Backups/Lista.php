<?php

namespace App\Livewire\AdministracionSistema\Backups;

use App\Jobs\BackupJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class Lista extends Component
{
    public function crear(): void
    {
        Cache::put('backup_status', ['status' => 'running'], 7200);
        BackupJob::dispatch()->onQueue('backup');
        $this->dispatch('toast-success', ['title' => __('Copia de seguridad en curso')]);
    }

    public function borrar(string $disco, string $fichero): void
    {
        $disk = Storage::disk($disco);

        if ($disk->exists($fichero)) {
            $disk->delete($fichero);
            $this->dispatch('toast-success', ['title' => __('Copia de seguridad borrada')]);
        }
    }

    public function render()
    {
        $status = Cache::get('backup_status');
        $backupRunning = $status && $status['status'] === 'running';

        $backups = collect(config('backup.backup.destination.disks'))
            ->flatMap(function ($nombreDisco) {
                $disk = Storage::disk($nombreDisco);

                return collect($disk->allFiles())
                    ->filter(fn ($fichero) => str_ends_with($fichero, '.zip'))
                    ->map(fn ($fichero) => [
                        'disco' => $nombreDisco,
                        'fichero' => $fichero,
                        'tamano' => $disk->size($fichero),
                        'fecha' => $disk->lastModified($fichero),
                    ]);
            })
            ->sortByDesc('fecha')
            ->values();

        return view('livewire.administracion-sistema.backups.lista', [
            'backups' => $backups,
            'backupRunning' => $backupRunning,
        ]);
    }
}

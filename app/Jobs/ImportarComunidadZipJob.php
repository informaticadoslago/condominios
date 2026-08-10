<?php

namespace App\Jobs;

use App\Services\Comunidades\ImportadorZipComunidad;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportarComunidadZipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(public string $rutaTemporal)
    {
    }

    public function handle(ImportadorZipComunidad $importador): void
    {
        try {
            $importador->importar($this->rutaTemporal);

            Log::info('ImportarComunidadZipJob completado', ['ruta' => $this->rutaTemporal]);
        } catch (\Throwable $e) {
            Log::error('ImportarComunidadZipJob falló', [
                'ruta' => $this->rutaTemporal,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Deja los logos en la carpeta pública desde la que los sirven las vistas.
 *
 * Los ficheros viven versionados en resources/images (viaja en el repo), pero el
 * destino public/storage/images/logo está en .gitignore y NO se despliega: sin
 * este comando, en producción welcome, login y el favicon salen con el logo roto.
 * Solo copia ficheros, así que es seguro ejecutarlo en un servidor real.
 */
class InstallResources extends Command
{
    protected $signature = 'xestionmusical:installresources';

    protected $description = 'Copia los logos de resources/images a public para que se vean en las vistas';

    /** Origen versionado y destino público (el que resuelve asset('storage/images/logo/...')). */
    const ORIGEN  = 'images';
    const DESTINO = 'storage/images/logo';

    public function handle()
    {
        $origen  = resource_path(self::ORIGEN);
        $destino = public_path(self::DESTINO);

        if (! File::isDirectory($origen)) {
            $this->error("No existe el directorio de origen: {$origen}");

            return Command::FAILURE;
        }

        $ficheros = File::files($origen);

        if ($ficheros === []) {
            $this->warn("No hay imágenes que copiar en {$origen}.");

            return Command::SUCCESS;
        }

        File::ensureDirectoryExists($destino, 0775);

        foreach ($ficheros as $fichero) {
            File::copy($fichero->getPathname(), $destino . '/' . $fichero->getFilename());
            $this->info("Copiado {$fichero->getFilename()} → public/" . self::DESTINO);
        }

        $this->alert('✅ Logos copiados a public.');

        return Command::SUCCESS;
    }
}

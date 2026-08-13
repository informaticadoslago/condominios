<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Deja los logos en la carpeta pública desde la que los sirven las vistas, y crea
 * los directorios de storage que necesita la app (por ahora, los de spatie/laravel-backup).
 *
 * Los logos viven versionados en resources/images (viaja en el repo), pero el
 * destino public/storage/images/logo está en .gitignore y NO se despliega: sin
 * este comando, en producción welcome, login y el favicon salen con el logo roto.
 * Los directorios de storage tampoco viajan por git (están vacíos) y, si nacen
 * por accidente con otro propietario (p. ej. una ejecución previa como root),
 * bloquean la escritura del usuario real de la app.
 *
 * No toca la base de datos: seguro ejecutarlo en cualquier momento en un servidor real.
 */
class InstallResources extends Command
{
    protected $signature = 'doslago:installresources';

    protected $description = 'Copia los logos a public y crea los directorios de storage que necesita la app';

    /** Origen versionado y destino público (el que resuelve asset('storage/images/logo/...')). */
    const ORIGEN  = 'images';
    const DESTINO = 'storage/images/logo';

    public function handle()
    {
        $this->copyLogos();
        $this->createDirs();

        return Command::SUCCESS;
    }

    protected function copyLogos(): void
    {
        $origen  = resource_path(self::ORIGEN);
        $destino = public_path(self::DESTINO);

        if (! File::isDirectory($origen)) {
            $this->error("No existe el directorio de origen: {$origen}");

            return;
        }

        $ficheros = File::files($origen);

        if ($ficheros === []) {
            $this->warn("No hay imágenes que copiar en {$origen}.");

            return;
        }

        File::ensureDirectoryExists($destino, 0775);

        foreach ($ficheros as $fichero) {
            File::copy($fichero->getPathname(), $destino . '/' . $fichero->getFilename());
            $this->info("Copiado {$fichero->getFilename()} → public/" . self::DESTINO);
        }

        $this->alert('✅ Logos copiados a public.');
    }

    /**
     * Crea los directorios que necesita spatie/laravel-backup (temporal y el de
     * destino con el slug de la app). Si el comando se lanza como root (instalación
     * inicial en un servidor nuevo), fuerza el propietario a www-data: si no, las
     * carpetas quedan de root y el proceso real de la app no puede escribir en ellas.
     */
    protected function createDirs(): void
    {
        $dirs = [
            config('backup.backup.temporary_directory'),
            rtrim(config('filesystems.disks.backups.root'), '/') . '/' . config('backup.backup.name'),
        ];

        $esRoot = function_exists('posix_geteuid') && posix_geteuid() === 0;

        foreach ($dirs as $dir) {
            File::ensureDirectoryExists($dir, 0775);

            if ($esRoot) {
                @chown($dir, 'www-data');
                @chgrp($dir, 'www-data');
            }

            $this->info("Directorio preparado: {$dir}");
        }

        $this->alert('✅ Directorios de backups preparados.');
    }
}

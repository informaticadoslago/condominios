<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Deja los logos en la carpeta pública desde la que los sirven las vistas, y crea
 * los directorios de storage que necesita la app (por ahora, los de spatie/laravel-backup).
 *
 * Los logos viven versionados en resources/images (viaja en el repo), pero el destino
 * public/images/logo está en .gitignore y NO se despliega: sin este comando, en
 * producción welcome, login y el favicon salen con el logo roto.
 * Igual el service worker de las notificaciones push (resources/js/sw.js → public/sw.js).
 * Los directorios de storage tampoco viajan por git (están vacíos) y, si nacen
 * por accidente con otro propietario (p. ej. una ejecución previa como root),
 * bloquean la escritura del usuario real de la app.
 *
 * El destino es una carpeta real dentro de public/, NO detrás del symlink
 * public/storage: un restore de backup (u otra operación que toque storage/app) no debe
 * poder dejar el logo roto por en medio.
 *
 * No toca la base de datos: seguro ejecutarlo en cualquier momento en un servidor real.
 */
class InstallResources extends Command
{
    protected $signature = 'doslago:installresources';

    protected $description = 'Copia los logos y el service worker a public y crea los directorios de storage que necesita la app';

    /** Origen versionado y destino público (el que resuelve asset('images/logo/...')). */
    const ORIGEN  = 'images';
    const DESTINO = 'images/logo';

    /**
     * Ficheros que cambian de nombre al copiarlos: en public llevan un nombre genérico
     * para que las vistas no dependan de la marca del logo de origen.
     */
    const RENOMBRAR = [
        'dosLago.png' => 'icono-app.png',
    ];

    /**
     * Service worker de las notificaciones push: origen versionado y destino en la raíz de public.
     * Se sirve tal cual, sin pasar por Vite: no puede llevar comentarios, llegarían al navegador.
     * Al llegar un push enseña la notificación (el servidor manda title, body, icon, data… con
     * los nombres de showNotification) y al pulsarla da el foco a la web abierta en data.url
     * o la abre (si no hay data.url, la portada).
     */
    const SERVICE_WORKER_ORIGEN  = 'js/sw.js';
    const SERVICE_WORKER_DESTINO = 'sw.js';

    public function handle()
    {
        $this->copyLogos();
        $this->copyServiceWorker();
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
            $nombre = self::RENOMBRAR[$fichero->getFilename()] ?? $fichero->getFilename();

            File::copy($fichero->getPathname(), $destino . '/' . $nombre);
            $this->info("Copiado {$fichero->getFilename()} → public/" . self::DESTINO . '/' . $nombre);
        }

        $this->alert('✅ Logos copiados a public.');
    }

    /**
     * El navegador solo deja que el service worker controle las páginas que cuelgan de la
     * carpeta desde la que se sirve: por eso va en la raíz de public y no en una subcarpeta.
     */
    protected function copyServiceWorker(): void
    {
        $origen = resource_path(self::SERVICE_WORKER_ORIGEN);

        if (! File::exists($origen)) {
            $this->error("No existe el service worker de origen: {$origen}");

            return;
        }

        File::copy($origen, public_path(self::SERVICE_WORKER_DESTINO));

        $this->info('Copiado resources/' . self::SERVICE_WORKER_ORIGEN . ' → public/' . self::SERVICE_WORKER_DESTINO);
        $this->alert('✅ Service worker copiado a public.');
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

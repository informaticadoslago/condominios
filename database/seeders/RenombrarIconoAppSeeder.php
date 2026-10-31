<?php
namespace Database\Seeders;

use App\Console\Commands\InstallResources;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Para los servidores donde doslago:installresources se lanzó antes de que copiara
 * dosLago.png con el nombre genérico icono-app.png: renombra el fichero que ya está
 * en public. No toca la base de datos y se puede relanzar.
 *
 *   php artisan db:seed --class=RenombrarIconoAppSeeder --force
 */
class RenombrarIconoAppSeeder extends Seeder
{
    public function run(): void
    {
        $destino = public_path(InstallResources::DESTINO);

        foreach (InstallResources::RENOMBRAR as $viejo => $nuevo) {
            if (File::exists("{$destino}/{$viejo}")) {
                File::move("{$destino}/{$viejo}", "{$destino}/{$nuevo}");
                $this->command->info("Renombrado {$viejo} → {$nuevo} en public/" . InstallResources::DESTINO);
            } elseif (! File::exists("{$destino}/{$nuevo}")) {
                $this->command->warn("No está {$viejo} ni {$nuevo} en public/" . InstallResources::DESTINO . ': lanza doslago:installresources.');
            }
        }
    }
}

<?php

namespace App\Console\Commands;

use Database\Seeders\DemoComunidadSeeder;
use Database\Seeders\DemoInmuebleSeeder;
use Database\Seeders\DemoPresupuestoSeeder;
use Illuminate\Console\Command;

/**
 * Va llamando, en orden, a los seeders de datos ficticios (demo): cada uno se añade
 * aquí a medida que se construye. Ninguno está en DatabaseSeeder (son solo para
 * poblar una demo, no para el arranque real de una instalación).
 */
class FakeSeed extends Command
{
    protected $signature = 'doslago:fakeseed';

    protected $description = 'Genera datos ficticios (demo): comunidades, propietarios, inmuebles...';

    /** En orden de dependencia: lo que necesite algo ya creado va después. */
    private const SEEDERS = [
        DemoComunidadSeeder::class,
        DemoInmuebleSeeder::class,
        DemoPresupuestoSeeder::class,
    ];

    public function __construct()
    {
        parent::__construct();

        // Datos ficticios: fuera de un entorno de desarrollo no se enseña siquiera.
        if (! config('app.debug')) {
            $this->hidden = true;
        }
    }

    public function handle()
    {
        if (! config('app.debug')) {
            $this->error('Este comando solo está disponible en modo debug.');

            return 1;
        }

        foreach (self::SEEDERS as $seeder) {
            $this->info("Ejecutando {$seeder}...");
            $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
        }

        $this->info('Datos ficticios generados.');

        return 0;
    }
}

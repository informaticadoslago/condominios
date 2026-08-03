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
 *
 * Se instancian y se llaman directamente (no vía `db:seed --class=...`) porque
 * DemoComunidadSeeder genera comunidades nuevas (nombre y CIF al azar) en cada
 * pasada, y hace falta pasar ESAS comunidades exactas a los siguientes seeders — así
 * nunca hay ambigüedad de "cuál es la comunidad demo" ni riesgo de tocar una real.
 */
class FakeSeed extends Command
{
    protected $signature = 'condominios:fakeseed';

    protected $description = 'Genera datos ficticios (demo): comunidades, propietarios, inmuebles...';

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

        $comunidadSeeder = (new DemoComunidadSeeder())->setCommand($this);
        $comunidades     = $comunidadSeeder->generar();

        $inmuebleSeeder = (new DemoInmuebleSeeder())->setCommand($this);
        $inmuebleSeeder->generar($comunidades);

        $presupuestoSeeder = (new DemoPresupuestoSeeder())->setCommand($this);
        $presupuestoSeeder->generar($comunidades['edificio1']);

        $this->info('Datos ficticios generados.');

        return 0;
    }
}

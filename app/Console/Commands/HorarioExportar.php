<?php

namespace App\Console\Commands;

use App\Models\Horario;
use App\Services\Horarios\HorarioExportador;
use Illuminate\Console\Command;

class HorarioExportar extends Command
{
    protected $signature = 'condominios:horario-exportar {horario : ID del horario}';

    protected $description = 'Exporta un horario entero (jornadas, asignaturas y sesiones) a un .zip en storage/app/coms';

    public function handle(HorarioExportador $exportador)
    {
        $horario = Horario::find($this->argument('horario'));

        if (! $horario) {
            $this->error("No existe el horario #{$this->argument('horario')}.");

            return 1;
        }

        $this->info("Exportando horario '{$horario->nombre}' (#{$horario->id})...");

        $nombreZip = $exportador->exportar($horario);

        $this->info("Exportación completada: storage/app/coms/{$nombreZip}");

        return 0;
    }
}

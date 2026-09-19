<?php

namespace App\Console\Commands;

use App\Models\Horario;
use App\Services\Horarios\HorarioEliminador;
use Illuminate\Console\Command;

class HorarioBorrar extends Command
{
    protected $signature = 'condominios:horario-borrar {horario : ID del horario}';

    protected $description = 'Borra un horario y TODOS sus datos relacionados (jornadas, asignaturas, sesiones). Irreversible.';

    public function handle(HorarioEliminador $eliminador)
    {
        $horario = Horario::find($this->argument('horario'));

        if (! $horario) {
            $this->error("No existe el horario #{$this->argument('horario')}.");

            return 1;
        }

        $this->warn("Se va a BORRAR PERMANENTEMENTE el horario '{$horario->nombre}' (#{$horario->id}) y todos sus datos:");
        $this->warn('jornadas, asignaturas y las sesiones que las asignan a cada día/hueco.');
        $this->warn('No hay papelera ni vuelta atrás (no se usan soft deletes en este proyecto).');

        if (! $this->confirm('¿Continuar?', false)) {
            $this->info('Cancelado.');

            return 0;
        }

        $eliminador->eliminar($horario);

        $this->info("Horario '{$horario->nombre}' borrado.");

        return 0;
    }
}

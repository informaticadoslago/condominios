<?php

namespace App\Console\Commands;

use App\Models\Horario;
use Illuminate\Console\Command;

class HorarioListar extends Command
{
    protected $signature = 'condominios:horarios-listar';

    protected $description = 'Lista horarios con id y nombre, una por línea.';

    protected $aliases = ['condominios:horario-listar'];

    public function handle(): int
    {
        $horarios = Horario::orderBy('id')->get();

        if ($horarios->isEmpty()) {
            $this->info('No hay horarios registrados.');

            return 0;
        }

        foreach ($horarios as $horario) {
            $this->line("{$horario->id} {$horario->nombre}");
        }

        return 0;
    }
}

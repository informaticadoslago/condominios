<?php

namespace App\Services\Horarios;

use App\Models\AccesoDirecto;
use App\Models\Asignatura;
use App\Models\Evento;
use App\Models\Horario;
use App\Models\HorarioSesion;
use App\Models\Modulo;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Borra un horario. Jornadas, módulos, asignaturas, horario_sesiones y eventos tienen ON DELETE
 * CASCADE hacia horarios (ver sus migraciones), así que en teoría la base de datos se
 * encarga de ellas sola; en la práctica, horario_sesiones.asignatura_id y
 * eventos.asignatura_id son RESTRICT hacia asignaturas, y MySQL no garantiza en qué
 * orden aplica varias cascadas que cuelgan del mismo padre: si intenta borrar la
 * asignatura antes que la sesión o el evento que aún la referencian, el borrado del
 * horario entero falla con un 1451. Lo mismo con asignaturas.modulo_id, RESTRICT hacia
 * modulos. Por eso aquí se borran horario_sesiones, eventos, asignaturas y modulos
 * explícitamente, en ese orden, antes de tocar el horario; jornadas sí puede cascada
 * sola (nada tiene un FK RESTRICT hacia ella).
 */
class HorarioEliminador
{
    public function eliminar(Horario $horario): void
    {
        DB::transaction(function () use ($horario) {
            $url = route('horario.entrar', $horario, false);

            AccesoDirecto::where('tipo', AccesoDirecto::TIPO_HORARIO)->where('url', $url)->delete();
            Role::where('name', $horario->nombreRol())->delete();

            HorarioSesion::where('horario_id', $horario->id)->delete();
            Evento::where('horario_id', $horario->id)->delete();
            Asignatura::where('horario_id', $horario->id)->delete();
            Modulo::where('horario_id', $horario->id)->delete();

            $horario->delete();
        });
    }
}

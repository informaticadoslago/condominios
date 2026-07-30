<?php

namespace App\Observers;

use App\Models\Alumno;
use App\Models\EstadoAlumno;
use App\Models\EstadoPersona;
use App\Models\EstadoSocio;
use App\Models\EstadoUsuario;
use App\Models\Persona;
use App\Models\Socio;
use App\Models\User;

class PersonaObserver
{
    /**
     * Inactivar/anonimizar una persona desde L12 inactiva también todos sus
     * "tipos de persona" (profesor, socio, alumno, usuario). Al inactivar se
     * guarda en personas.estados_previos un snapshot con el estado que tenía
     * cada rol, para poder restaurarlo al reactivar. Anonimizar (LOPD) es
     * irreversible: inactiva los roles y no deja nada que restaurar.
     */
    public function updated(Persona $persona): void
    {
        if (! $persona->wasChanged('estado_id')) {
            return;
        }

        $anterior = (int) $persona->getOriginal('estado_id');
        $nuevo    = (int) $persona->estado_id;

        if ($nuevo == EstadoPersona::PERSONA_INACTIVA && $anterior == EstadoPersona::PERSONA_ACTIVA) {
            $this->inactivarRoles($persona, guardarSnapshot: true);
        } elseif ($nuevo == EstadoPersona::PERSONA_ANONIMA) {
            $this->inactivarRoles($persona, guardarSnapshot: false);
        } elseif ($nuevo == EstadoPersona::PERSONA_ACTIVA && $anterior == EstadoPersona::PERSONA_INACTIVA) {
            $this->restaurarRoles($persona);
        }
    }

    private function inactivarRoles(Persona $persona, bool $guardarSnapshot): void
    {
        $snapshot = [];

        if ($socio = Socio::where('persona_id', $persona->id)->first()) {
            $snapshot['socio'] = $socio->estado_id;
            $socio->estado_id  = EstadoSocio::SOCIO_INACTIVO;
            $socio->save();
        }

        if ($usuario = User::where('persona_id', $persona->id)->first()) {
            $snapshot['usuario'] = $usuario->estado_id;
            $usuario->estado_id  = EstadoUsuario::USUARIO_INACTIVO;
            $usuario->save();
        }

        if ($alumno = Alumno::where('persona_id', $persona->id)->first()) {
            $snapshot['alumno'] = $alumno->estado_id;
            $alumno->estado_id  = EstadoAlumno::ALUMNO_BAJA;
            $alumno->save();
        }

        $persona->forceFill([
            'estados_previos' => $guardarSnapshot ? $snapshot + ['fecha' => now()->toDateTimeString()] : null,
        ])->saveQuietly();
    }

    /**
     * Restaura cada rol al estado del snapshot, pero solo si sigue inactivo:
     * si alguien reactivó un rol a mano entre medias, no se le pisa.
     */
    private function restaurarRoles(Persona $persona): void
    {
        $snapshot = $persona->estados_previos ?? [];

        if (isset($snapshot['socio'])
            && ($socio = Socio::where('persona_id', $persona->id)->first())
            && $socio->estado_id == EstadoSocio::SOCIO_INACTIVO) {
            $socio->estado_id = $snapshot['socio'];
            $socio->save();
        }

        if (isset($snapshot['usuario'])
            && ($usuario = User::where('persona_id', $persona->id)->first())
            && $usuario->estado_id == EstadoUsuario::USUARIO_INACTIVO) {
            $usuario->estado_id = $snapshot['usuario'];
            $usuario->save();
        }

        if (isset($snapshot['alumno'])
            && ($alumno = Alumno::where('persona_id', $persona->id)->first())
            && $alumno->estado_id == EstadoAlumno::ALUMNO_BAJA) {
            $alumno->estado_id = $snapshot['alumno'];
            $alumno->save();
        }

        $persona->forceFill(['estados_previos' => null])->saveQuietly();
    }
}

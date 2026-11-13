<?php

namespace App\Http\Controllers;

use App\Models\Horario;
use App\Services\Horarios\CalendarioEventosIcs;
use Illuminate\Http\Response;

/**
 * La dirección de suscripción al calendario de eventos (exámenes, juntas…) de un horario. Es pública: la
 * descargan los servidores de Google (o el iPhone) sin sesión, así que el horario sale
 * del código secreto de la URL (horarios.token_calendario), no de la sesión.
 */
class EventosIcsController extends Controller
{
    public function __invoke(string $token, CalendarioEventosIcs $calendario): Response
    {
        $horario = Horario::where('token_calendario', $token)->firstOrFail();

        return response($calendario->generar($horario), 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="eventos.ics"',
        ]);
    }
}

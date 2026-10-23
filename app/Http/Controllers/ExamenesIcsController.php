<?php

namespace App\Http\Controllers;

use App\Models\Horario;
use App\Services\Horarios\CalendarioExamenesIcs;
use Illuminate\Http\Response;

/**
 * La dirección de suscripción al calendario de exámenes de un horario. Es pública: la
 * descargan los servidores de Google (o el iPhone) sin sesión, así que el horario sale
 * del código secreto de la URL (horarios.token_calendario), no de la sesión.
 */
class ExamenesIcsController extends Controller
{
    public function __invoke(string $token, CalendarioExamenesIcs $calendario): Response
    {
        $horario = Horario::where('token_calendario', $token)->firstOrFail();

        return response($calendario->generar($horario), 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="examenes.ics"',
        ]);
    }
}

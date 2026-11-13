<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Horario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * La lista de eventos del calendario en papel, por meses y sin la rejilla del horario: la misma que
 * está en pantalla, con los filtros que se le hayan puesto allí (Mostrar, Tipo y Asignatura).
 */
class EventosPdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filtros = $request->validate([
            'periodo'       => ['nullable', Rule::in([Evento::PERIODO_FUTUROS, Evento::PERIODO_TODOS])],
            'tipo_evento_id' => ['nullable', 'integer'],
            'asignatura_id' => ['nullable', 'integer'],
        ]);

        // El horario es el de la sesión, no el que venga en la URL (esta ruta ya cuelga de
        // horario.activa): los filtros se piden, el dueño de los eventos no. Una
        // asignatura de otro horario, simplemente, no trae nada.
        $horario = Horario::findOrFail(session('horario_actual_id'));

        $pdf = Pdf::loadView('pdf.eventos', [
            'horario' => $horario,
            'eventosPorMes' => Evento::porMes(
                $horario->id,
                ($filtros['periodo'] ?? Evento::PERIODO_FUTUROS) === Evento::PERIODO_FUTUROS,
                ($filtros['asignatura_id'] ?? null) ? (int) $filtros['asignatura_id'] : null,
                ($filtros['tipo_evento_id'] ?? null) ? (int) $filtros['tipo_evento_id'] : null,
            ),
        ])->setPaper('a4', 'portrait');

        // Inline: se abre en otra pestaña y de ahí se imprime o se guarda.
        return $pdf->stream('calendario-'.Str::slug($horario->nombre).'.pdf');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use App\Models\Horario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * La lista de exámenes en papel, por meses y sin la rejilla del horario: la misma que
 * está en pantalla, con los filtros que se le hayan puesto allí (Mostrar y Asignatura).
 */
class ExamenesPdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filtros = $request->validate([
            'periodo'       => ['nullable', Rule::in([Examen::PERIODO_FUTUROS, Examen::PERIODO_TODOS])],
            'asignatura_id' => ['nullable', 'integer'],
        ]);

        // El horario es el de la sesión, no el que venga en la URL (esta ruta ya cuelga de
        // horario.activa): los filtros se piden, el dueño de los exámenes no. Una
        // asignatura de otro horario, simplemente, no trae nada.
        $horario = Horario::findOrFail(session('horario_actual_id'));

        $pdf = Pdf::loadView('pdf.examenes', [
            'horario' => $horario,
            'examenesPorMes' => Examen::porMes(
                $horario->id,
                ($filtros['periodo'] ?? Examen::PERIODO_FUTUROS) === Examen::PERIODO_FUTUROS,
                ($filtros['asignatura_id'] ?? null) ? (int) $filtros['asignatura_id'] : null,
            ),
        ])->setPaper('a4', 'portrait');

        // Inline: se abre en otra pestaña y de ahí se imprime o se guarda.
        return $pdf->stream('examenes-'.Str::slug($horario->nombre).'.pdf');
    }
}

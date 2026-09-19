<?php

namespace App\Http\Controllers;

use App\Models\Horario;
use App\Models\Jornada;
use App\Support\DiaSemana;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * El horario semanal en papel: el mismo que está en pantalla (vista, no el modal de
 * edición), para imprimirlo o guardarlo. El horario es el de la sesión, no viene en
 * la URL: esta ruta ya cuelga de horario.activa.
 */
class HorarioRejillaPdfController extends Controller
{
    public function __invoke(): Response
    {
        $horario = Horario::with(['jornadas', 'sesiones.asignatura'])->findOrFail(session('horario_actual_id'));

        $diasConfig = [];
        $maxSlots = 0;

        $minutosReferencia = $horario->alinear_horas ? $horario->minutosReferenciaJornadas() : null;

        foreach ($horario->jornadasPorDia() as $diaSemana => $jornadas) {
            $combinado = Jornada::combinarSlots($jornadas, $horario->duracion_sesion_minutos ?? 0, $minutosReferencia);

            $diasConfig[$diaSemana] = [
                'nombre' => DiaSemana::from($diaSemana)->nombre(),
                'slots' => $combinado['slots'],
            ];

            $maxSlots = max($maxSlots, $combinado['total_filas']);
        }

        $asignaciones = [];
        foreach ($horario->sesiones as $sesion) {
            if ($sesion->asignatura) {
                $asignaciones[$sesion->dia_semana][$sesion->sesion_numero] = [
                    'nombre' => $sesion->asignatura->nombre,
                    'fondo' => $sesion->asignatura->colorFondo(),
                    'texto' => $sesion->asignatura->colorTexto(),
                ];
            }
        }

        $horasFilas = Jornada::horasPorFila($diasConfig, $maxSlots, $horario->duracion_sesion_minutos ?? 0);

        // Hasta 5 columnas de día caben en vertical; con 6 o 7 (semana completa) hace
        // falta apaisado para que no se queden estrechas.
        $orientacion = count($diasConfig) <= 5 ? 'portrait' : 'landscape';

        $pdf = Pdf::loadView('pdf.horario-semanal', [
            'horario' => $horario,
            'diasConfig' => $diasConfig,
            'maxSlots' => $maxSlots,
            'asignaciones' => $asignaciones,
            'horasFilas' => $horasFilas,
        ])->setPaper('a4', $orientacion);

        return $pdf->stream('horario-'.Str::slug($horario->nombre).'.pdf');
    }
}

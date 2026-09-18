<?php

namespace App\Http\Controllers;

use App\Models\Horario;
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
        $horario = Horario::with(['dias', 'sesiones.asignatura'])->findOrFail(session('horario_actual_id'));

        $diasConfig = [];
        $maxSlots   = 0;

        foreach ($horario->dias->sortBy('dia_semana') as $dia) {
            $slots = $dia->calcularSlotsAlineados($horario->duracion_sesion_minutos ?? 0);

            $diasConfig[$dia->dia_semana] = [
                'nombre' => DiaSemana::from($dia->dia_semana)->nombre(),
                'slots'  => $slots,
            ];

            $maxSlots = max($maxSlots, count($slots));
        }

        $asignaciones = [];
        foreach ($horario->sesiones as $sesion) {
            if ($sesion->asignatura) {
                $asignaciones[$sesion->dia_semana][$sesion->sesion_numero] = [
                    'nombre' => $sesion->asignatura->nombre,
                    'fondo'  => $sesion->asignatura->colorFondo(),
                    'texto'  => $sesion->asignatura->colorTexto(),
                ];
            }
        }

        // Hasta 5 columnas de día caben en vertical; con 6 o 7 (semana completa) hace
        // falta apaisado para que no se queden estrechas.
        $orientacion = count($diasConfig) <= 5 ? 'portrait' : 'landscape';

        $pdf = Pdf::loadView('pdf.horario-semanal', [
            'horario'      => $horario,
            'diasConfig'   => $diasConfig,
            'maxSlots'     => $maxSlots,
            'asignaciones' => $asignaciones,
        ])->setPaper('a4', $orientacion);

        return $pdf->stream('horario-'.Str::slug($horario->nombre).'.pdf');
    }
}

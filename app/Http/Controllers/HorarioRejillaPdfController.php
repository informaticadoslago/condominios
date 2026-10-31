<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use App\Models\Horario;
use App\Models\Jornada;
use App\Services\Horarios\ColocacionSesiones;
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
        $horario = Horario::with(['jornadas.recreos', 'sesiones.asignatura.modulo'])->findOrFail(session('horario_actual_id'));

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
                    'modulo' => $sesion->asignatura->modulo?->nombre,
                    'fondo' => $sesion->asignatura->colorFondo(),
                    'texto' => $sesion->asignatura->colorTexto(),
                ];
            }
        }

        $horasFilas = Jornada::horasPorFila($diasConfig, $maxSlots, $horario->duracion_sesion_minutos ?? 0);

        // Debajo del horario, los exámenes que quedan (los de hoy incluidos), por meses.
        $examenesPorMes = Examen::porMes($horario->id);

        // Hasta 5 columnas de día caben en vertical; con 6 o 7 (semana completa) hace
        // falta apaisado para que no se queden estrechas.
        $orientacion = count($diasConfig) <= 5 ? 'portrait' : 'landscape';

        $pdf = Pdf::loadView('pdf.horario-semanal', [
            'horario' => $horario,
            'rejillaProfesor' => $horario->esDeProfesor() ? $this->rejillaProfesor($horario, $orientacion) : null,
            'diasConfig' => $diasConfig,
            'maxSlots' => $maxSlots,
            'asignaciones' => $asignaciones,
            'horasFilas' => $horasFilas,
            'examenesPorMes' => $examenesPorMes,
        ])->setPaper('a4', $orientacion);

        return $pdf->stream('horario-'.Str::slug($horario->nombre).'.pdf');
    }

    /**
     * El horario de profesor como tabla de filas de 5 minutos (o menos, si alguna hora no
     * cae en múltiplo de 5, ver ColocacionSesiones::paso()): cada sesión y cada recreo
     * ocupan, con rowspan, las filas de su tramo; los huecos y lo que queda fuera de la
     * jornada van fila a fila, para que se vea la cuadrícula. El alto de fila se ajusta
     * para que el día entero quepa en una hoja.
     *
     * @return array{filas: int, alto: float, etiquetas: array, celdas: array}|null
     */
    private function rejillaProfesor(Horario $horario, string $orientacion): ?array
    {
        $colocacion = new ColocacionSesiones($horario);
        $limites = $colocacion->limitesSemana();

        if (! $limites) {
            return null;
        }

        $paso = $colocacion->paso();
        $filas = intdiv($limites['fin'] - $limites['inicio'] + $paso - 1, $paso);
        $minuto = fn (int $fila) => $limites['inicio'] + $fila * $paso;

        // La columna de horas: una celda por cada media hora, con la hora arriba.
        $etiquetas = [];
        for ($fila = 0; $fila < $filas; $fila = $siguiente) {
            $siguiente = $fila + 1;
            while ($siguiente < $filas && $minuto($siguiente) % 30 !== 0) {
                $siguiente++;
            }

            $etiquetas[$fila] = [
                'hora' => $minuto($fila) % 30 === 0 ? ColocacionSesiones::hora($minuto($fila)) : '',
                'rowspan' => $siguiente - $fila,
            ];
        }

        $sesiones = $horario->sesiones->keyBy(fn ($s) => $s->dia_semana.'-'.$s->sesion_numero);
        $celdas = [];

        foreach ($colocacion->dias() as $dia) {
            foreach ($colocacion->tramos($dia, $limites['inicio'], $minuto($filas), $paso) as $tramo) {
                $primera = intdiv($tramo['inicio'] - $limites['inicio'], $paso);
                $numFilas = intdiv($tramo['fin'] - $tramo['inicio'], $paso);

                if (in_array($tramo['tipo'], ['hueco', 'fuera'], true)) {
                    for ($f = $primera; $f < $primera + $numFilas; $f++) {
                        $celdas[$dia][$f] = ['tipo' => $tramo['tipo'], 'rowspan' => 1, 'media' => $minuto($f) % 30 === 0];
                    }

                    continue;
                }

                $asignatura = $tramo['tipo'] === 'sesion' ? $sesiones->get($dia.'-'.$tramo['numero'])?->asignatura : null;

                $celdas[$dia][$primera] = [
                    'tipo' => $tramo['tipo'],
                    'rowspan' => $numFilas,
                    'media' => $minuto($primera) % 30 === 0,
                    'horas' => ColocacionSesiones::hora($tramo['inicio']).'–'.ColocacionSesiones::hora($tramo['fin']),
                    'nombre' => $asignatura?->nombre,
                    'aula' => $asignatura?->aula,
                    'modulo' => $asignatura?->modulo?->nombre,
                    'fondo' => $asignatura?->colorFondo(),
                    'texto' => $asignatura?->colorTexto(),
                ];
            }
        }

        // Milímetros que quedan para la tabla en la hoja, quitando márgenes y cabecera.
        $disponible = $orientacion === 'portrait' ? 235 : 150;

        return [
            'filas' => $filas,
            'alto' => round(min(2.2 * $paso / 5, $disponible / max(1, $filas)), 2),
            'etiquetas' => $etiquetas,
            'celdas' => $celdas,
        ];
    }
}

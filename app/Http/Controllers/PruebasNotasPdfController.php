<?php

namespace App\Http\Controllers;

use App\Models\Asignatura;
use App\Models\Evaluacion;
use App\Services\Horarios\CuadroEvaluacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Las notas de una asignatura en una evaluación, en papel (ver CuadroEvaluacion): una fila
 * por alumno y una columna por prueba activa, en el orden de impresión de las pruebas, con
 * la nota sobre 10. De una prueba con partes salen primero sus partes y después ella, con
 * la nota final. Al final de cada hoja, la media de la evaluación y su nota, y debajo la
 * media de cada columna. Es el mismo PDF desde la lista de pruebas y desde la pantalla de la evaluación.
 */
class PruebasNotasPdfController extends Controller
{
    /** Alumnos por hoja: con la letra grande del alumno, los que caben en un A4 apaisado. */
    private const ALUMNOS_POR_HOJA = 20;

    /** Columnas de notas por hoja: las que caben a lo ancho al lado de la columna de alumnos. */
    private const COLUMNAS_POR_HOJA = 10;

    /** De ellas, las de pruebas: las otras dos son la media de la evaluación y su nota, en todas las hojas. */
    private const PRUEBAS_POR_HOJA = self::COLUMNAS_POR_HOJA - 2;

    public function __invoke(Request $request, Asignatura $asignatura): Response
    {
        $datos = $request->validate(['evaluacion_id' => ['required', 'integer']]);
        $evaluacion = Evaluacion::with('curso')->findOrFail($datos['evaluacion_id']);

        // Solo asignaturas y evaluaciones del horario abierto, y solo si es de profesor.
        abort_unless(
            $asignatura->horario_id == session('horario_actual_id')
                && $asignatura->horario?->esDeProfesor()
                && $evaluacion->curso?->horario_id == $asignatura->horario_id,
            404
        );

        $cuadro = new CuadroEvaluacion($asignatura, $evaluacion);

        // Una hoja por cada tanda de alumnos y de pruebas (con sus partes delante): primero
        // todos los alumnos con las primeras pruebas y después, si hay más, otra vez los
        // alumnos con las siguientes. Cada hoja acaba con la media de la evaluación y su nota,
        // para que no queden solas en otra hoja, y la última de alumnos lleva la fila de medias.
        $hojas = [];
        $tandasApuntes = $cuadro->apuntes->chunk(self::ALUMNOS_POR_HOJA);
        foreach ($cuadro->columnas->chunk(self::PRUEBAS_POR_HOJA) as $tandaPruebas) {
            foreach ($tandasApuntes as $i => $tandaApuntes) {
                $hojas[] = [
                    'columnas' => $tandaPruebas->concat(['calculada', 'final']),
                    'apuntes'  => $tandaApuntes,
                    'medias'   => $i === $tandasApuntes->keys()->last(),
                ];
            }
        }

        $pdf = Pdf::loadView('pdf.pruebas-notas', [
            'cuadro' => $cuadro,
            'hojas'  => $hojas,
            // En milímetros, sin contar el relleno de las celdas (unos 3 por columna): el A4
            // apaisado deja 277 entre márgenes.
            'anchoAlumno'  => 76,
            'anchoColumna' => 163 / self::COLUMNAS_POR_HOJA,
        ])->setPaper('a4', 'landscape');

        // Inline: se abre en otra pestaña y de ahí se imprime o se guarda.
        return $pdf->stream('notas-'.Str::slug("{$asignatura->nombre} {$evaluacion->curso->nombre} {$evaluacion->nombre}").'.pdf');
    }
}

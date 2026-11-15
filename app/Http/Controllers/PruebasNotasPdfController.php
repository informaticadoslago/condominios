<?php

namespace App\Http\Controllers;

use App\Models\Asignatura;
use App\Models\AsignaturaAlumno;
use App\Models\Evaluacion;
use App\Models\Prueba;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Las notas de una asignatura en una evaluación, en papel: una fila por alumno y una
 * columna por prueba activa, en el orden de impresión de las pruebas, con la nota sobre 10.
 */
class PruebasNotasPdfController extends Controller
{
    /** Alumnos por hoja: con la letra grande del alumno, los que caben en un A4 apaisado. */
    private const ALUMNOS_POR_HOJA = 20;

    /** Pruebas por hoja: las que caben a lo ancho al lado de la columna de alumnos. */
    private const PRUEBAS_POR_HOJA = 10;

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

        $pruebas = Prueba::where('asignatura_id', $asignatura->id)
            ->where('evaluacion_id', $evaluacion->id)
            ->where('estado_id', Prueba::ESTADO_ACTIVO)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        // Los alumnos activos y, además, los de baja que tengan nota en alguna de las pruebas.
        $delInforme = fn ($q) => $q->whereIn('prueba_id', $pruebas->modelKeys());
        $apuntes = AsignaturaAlumno::with(['alumno', 'notas' => $delInforme])
            ->where('asignatura_id', $asignatura->id)
            ->where(fn ($q) => $q
                ->where('estado_id', AsignaturaAlumno::ESTADO_ACTIVO)
                ->orWhereHas('notas', $delInforme))
            ->get()
            // Sin acentos, para que «Gándara» vaya antes que «García».
            ->sortBy(fn (AsignaturaAlumno $a) => Str::lower(Str::ascii("{$a->alumno->apellido1} {$a->alumno->apellido2} {$a->alumno->nombre}")))
            ->values();

        // Una hoja por cada tanda de alumnos y de pruebas: primero todos los alumnos con las
        // primeras pruebas y después, si hay más pruebas, otra vez los alumnos con las siguientes.
        $hojas = [];
        foreach ($pruebas->chunk(self::PRUEBAS_POR_HOJA) as $tandaPruebas) {
            foreach ($apuntes->chunk(self::ALUMNOS_POR_HOJA) as $tandaApuntes) {
                $hojas[] = ['pruebas' => $tandaPruebas, 'apuntes' => $tandaApuntes];
            }
        }

        $pdf = Pdf::loadView('pdf.pruebas-notas', [
            'asignatura' => $asignatura,
            'evaluacion' => $evaluacion,
            'hojas'      => $hojas,
            // En milímetros, sin contar el relleno de las celdas (unos 3 por columna): el A4
            // apaisado deja 277 entre márgenes.
            'anchoAlumno' => 76,
            'anchoPrueba' => 163 / self::PRUEBAS_POR_HOJA,
        ])->setPaper('a4', 'landscape');

        // Inline: se abre en otra pestaña y de ahí se imprime o se guarda.
        return $pdf->stream('notas-'.Str::slug("{$asignatura->nombre} {$evaluacion->curso->nombre} {$evaluacion->nombre}").'.pdf');
    }
}

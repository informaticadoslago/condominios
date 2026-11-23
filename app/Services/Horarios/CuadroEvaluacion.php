<?php

namespace App\Services\Horarios;

use App\Models\Asignatura;
use App\Models\AsignaturaAlumno;
use App\Models\Evaluacion;
use App\Models\EvaluacionNota;
use App\Models\Prueba;
use App\Models\PruebaNota;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Las notas de una asignatura en una evaluación: una fila por alumno y una columna por
 * prueba activa, con la nota sobre 10, y al final la nota de la evaluación. Lo usan la
 * pantalla de la evaluación (Pruebas\NotasEvaluacion) y su PDF (PruebasNotasPdfController).
 *
 * La nota de la evaluación es la media de las pruebas según su peso, con la misma regla
 * que la de una prueba con partes (ver Prueba::mediaDePartes) pero sin nota mínima:
 *  - sin nota en ninguna prueba, no hay nota;
 *  - la prueba sin nota cuenta como 0;
 *  - si los pesos no suman 100, la media se reparte entre el peso que hay.
 * Las partes salen en el cuadro, pero cuentan a través de su prueba. La nota se puede
 * retocar a mano (EvaluacionNota) y el retoque vale mientras la media no cambie.
 */
class CuadroEvaluacion
{
    /** @var Collection<int, Prueba> las pruebas activas que cuentan (sin las partes), en su orden */
    public Collection $pruebas;

    /** @var Collection<int, Prueba> las columnas: cada prueba, con sus partes activas delante */
    public Collection $columnas;

    /** @var Collection<int, AsignaturaAlumno> los alumnos activos y los de baja con alguna nota */
    public Collection $apuntes;

    /** @var array<int, array<int, ?float>> [id del apunte => [id de la prueba => nota sobre 10 o null]] */
    public array $notas = [];

    /** @var array<int, array{calculada: ?float, final: ?float, retocada: bool}> [id del apunte => su nota de la evaluación] */
    public array $finales = [];

    /** @var array<int, ?float> [id de la prueba => media de la columna] */
    public array $medias = [];

    public ?float $mediaCalculada = null;

    public ?float $mediaFinal = null;

    /** Lo que suman los pesos de las pruebas que cuentan. */
    public float $sumaPesos = 0;

    public function __construct(public readonly Asignatura $asignatura, public readonly Evaluacion $evaluacion)
    {
        $activas = fn ($q) => $q->where('estado_id', Prueba::ESTADO_ACTIVO);
        $this->pruebas = Prueba::with(['partes' => $activas])
            ->where('asignatura_id', $asignatura->id)
            ->where('evaluacion_id', $evaluacion->id)
            ->whereNull('prueba_id')
            ->where('estado_id', Prueba::ESTADO_ACTIVO)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
        $this->columnas = $this->pruebas->toBase()
            ->flatMap(fn (Prueba $prueba) => [...$prueba->partes, $prueba])
            ->values();
        $this->sumaPesos = (float) $this->pruebas->sum('peso');

        // Lo que hay guardado, por alumno: la nota sobre 10 de cada prueba suelta o parte, y
        // la final (media de sus partes o su retoque) de cada prueba con partes.
        $porApunte = [];
        $sueltas = $this->columnas->reject(fn (Prueba $prueba) => $prueba->con_partes);
        foreach (PruebaNota::whereIn('prueba_id', $sueltas->pluck('id')->all())->get() as $nota) {
            $porApunte[$nota->asignatura_alumno_id][$nota->prueba_id] = $nota->nota_sobre_10;
        }
        foreach ($this->columnas->where('con_partes', true) as $prueba) {
            foreach ($prueba->notasFinales() as $apunteId => $notas) {
                if ($notas['final'] !== null) {
                    $porApunte[$apunteId][$prueba->id] = $notas['final'];
                }
            }
        }

        $this->apuntes = AsignaturaAlumno::with('alumno')
            ->where('asignatura_id', $asignatura->id)
            ->where(fn ($q) => $q
                ->where('estado_id', AsignaturaAlumno::ESTADO_ACTIVO)
                ->orWhereIn('id', array_keys($porApunte)))
            ->get()
            // Sin acentos, para que «Gándara» vaya antes que «García».
            ->sortBy(fn (AsignaturaAlumno $a) => Str::lower(Str::ascii("{$a->alumno->apellido1} {$a->alumno->apellido2} {$a->alumno->nombre}")))
            ->values();

        $retoques = EvaluacionNota::where('evaluacion_id', $evaluacion->id)
            ->whereIn('asignatura_alumno_id', $this->apuntes->modelKeys())
            ->get()
            ->keyBy('asignatura_alumno_id');

        foreach ($this->apuntes as $apunte) {
            // Una prueba de Entregado / No entregado no tiene «sin nota»: el alumno activo
            // que no la tiene es que no ha entregado, y eso es un 0.
            $activo = $apunte->estado_id == AsignaturaAlumno::ESTADO_ACTIVO;
            $this->notas[$apunte->id] = $this->columnas->mapWithKeys(fn (Prueba $prueba) => [
                $prueba->id => $porApunte[$apunte->id][$prueba->id]
                    ?? ($activo && ! $prueba->con_partes && $prueba->esDeEntrega() ? 0.0 : null),
            ])->all();

            $calculada = $this->media($this->notas[$apunte->id]);
            $retoque = $retoques->get($apunte->id);
            $vale = $retoque && $calculada !== null && abs($retoque->media - $calculada) < 0.005;

            $this->finales[$apunte->id] = [
                'calculada' => $calculada,
                'final'     => $vale ? $retoque->nota : $calculada,
                'retocada'  => $vale,
            ];
        }

        // Las medias de cada columna: de los alumnos activos con nota en ella.
        foreach ($this->columnas as $prueba) {
            $this->medias[$prueba->id] = $this->mediaDeColumna(fn (AsignaturaAlumno $a) => $this->notas[$a->id][$prueba->id]);
        }
        $this->mediaCalculada = $this->mediaDeColumna(fn (AsignaturaAlumno $a) => $this->finales[$a->id]['calculada']);
        $this->mediaFinal = $this->mediaDeColumna(fn (AsignaturaAlumno $a) => $this->finales[$a->id]['final']);
    }

    /** La nota de la evaluación a partir de las notas sobre 10 de un alumno: ver arriba. */
    private function media(array $notas): ?float
    {
        $conNota = $this->pruebas->filter(fn (Prueba $prueba) => ($notas[$prueba->id] ?? null) !== null);

        if ($this->sumaPesos <= 0 || $conNota->isEmpty()) {
            return null;
        }

        return round($this->pruebas->sum(fn (Prueba $prueba) => (float) ($notas[$prueba->id] ?? 0) * $prueba->peso) / $this->sumaPesos, 2);
    }

    /** La media de lo que da $valor para cada alumno activo, sin contar a los que no tienen nota. */
    private function mediaDeColumna(callable $valor): ?float
    {
        $valores = $this->apuntes
            ->filter(fn (AsignaturaAlumno $a) => $a->estado_id == AsignaturaAlumno::ESTADO_ACTIVO)
            ->map(fn (AsignaturaAlumno $a) => $valor($a))
            ->reject(fn ($nota) => $nota === null);

        return $valores->isEmpty() ? null : round($valores->avg(), 2);
    }
}

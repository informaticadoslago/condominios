<?php

namespace App\Livewire\Pruebas;

use App\Models\Asignatura;
use App\Models\Evaluacion;
use App\Models\EvaluacionNota;
use App\Models\Prueba;
use App\Services\Horarios\CuadroEvaluacion;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Notas de una asignatura en una evaluación: una fila por alumno con su nota sobre 10 en
 * cada prueba, la media de las pruebas según su peso (ver CuadroEvaluacion) y la nota de
 * la evaluación, que parte de esa media y se puede retocar a mano o con las flechas, al
 * entero de arriba o de abajo. El retoque vale mientras la media no cambie. Debajo, la
 * media de cada columna.
 */
class NotasEvaluacion extends Component
{
    #[Locked]
    public int $asignaturaId;

    #[Locked]
    public int $evaluacionId;

    /** [id del apunte => nota de la evaluación], como texto y con coma decimal. */
    public array $finales = [];

    public function mount(Asignatura $asignatura, Evaluacion $evaluacion)
    {
        // Solo asignaturas y evaluaciones del horario abierto, y solo si es de profesor.
        abort_unless(
            $asignatura->horario_id == session('horario_actual_id')
                && $asignatura->horario?->esDeProfesor()
                && $evaluacion->curso?->horario_id == $asignatura->horario_id,
            404
        );

        $this->asignaturaId = $asignatura->id;
        $this->evaluacionId = $evaluacion->id;

        foreach ($this->cuadro()->finales as $apunteId => $notas) {
            $this->finales[$apunteId] = Prueba::numero($notas['final']);
        }
    }

    private function cuadro(): CuadroEvaluacion
    {
        return new CuadroEvaluacion(
            Asignatura::with('modulo')->findOrFail($this->asignaturaId),
            Evaluacion::with('curso')->findOrFail($this->evaluacionId)
        );
    }

    /** Se guarda el retoque de la casilla que se acaba de dejar; vacía, vuelve a la media. */
    public function updatedFinales($valor, $apunteId)
    {
        $this->resetValidation("finales.{$apunteId}");

        $texto = str_replace(',', '.', trim((string) $valor));
        if ($texto !== '' && ! (is_numeric($texto) && $texto >= 0 && $texto <= 10)) {
            $this->addError("finales.{$apunteId}", __('Un número entre 0 y :maximo', ['maximo' => 10]));

            return;
        }

        $this->retocar($apunteId, $texto === '' ? null : round((float) $texto, 2));
    }

    /** Las flechas junto a la nota: al entero siguiente (+1) o al anterior (-1). */
    public function redondear($apunteId, int $sentido)
    {
        $final = $this->cuadro()->finales[$apunteId]['final'] ?? null;
        if ($final === null) {
            return;
        }

        $this->resetValidation("finales.{$apunteId}");
        $this->retocar($apunteId, $sentido > 0 ? min(10, floor($final) + 1) : max(0, ceil($final) - 1));
    }

    /**
     * Deja $nota como nota de la evaluación del alumno; null (o la misma que la media)
     * quita el retoque. Sin nota en ninguna prueba no hay media que retocar. Solo alumnos
     * del cuadro: la evaluación es del curso y la comparten todas sus asignaturas.
     */
    private function retocar($apunteId, ?float $nota): void
    {
        $finales = $this->cuadro()->finales;
        if (! array_key_exists($apunteId, $finales)) {
            return;
        }

        $calculada = $finales[$apunteId]['calculada'];
        $retoque = ['evaluacion_id' => $this->evaluacionId, 'asignatura_alumno_id' => (int) $apunteId];

        if ($calculada === null || $nota === null || abs($nota - $calculada) < 0.005) {
            EvaluacionNota::where($retoque)->delete();
            $nota = $calculada;
        } else {
            EvaluacionNota::updateOrCreate($retoque, ['media' => $calculada, 'nota' => $nota]);
        }

        $this->finales[$apunteId] = Prueba::numero($nota);
    }

    public function render()
    {
        return view('livewire.pruebas.notas-evaluacion', [
            'cuadro' => $this->cuadro(),
        ]);
    }
}

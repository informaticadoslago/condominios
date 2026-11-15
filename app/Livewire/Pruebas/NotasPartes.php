<?php

namespace App\Livewire\Pruebas;

use App\Models\Asignatura;
use App\Models\AsignaturaAlumno;
use App\Models\Prueba;
use App\Models\PruebaNota;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Notas de una prueba con partes: una fila por alumno, con su nota sobre 10 en cada parte
 * (que se pone en la pantalla de notas de esa parte) y la nota final de la prueba, que es
 * la media de las partes (ver Prueba::mediaDePartes) y se puede retocar a mano o con las
 * flechas, al entero de arriba o de abajo. El retoque vale mientras la media no cambie.
 */
class NotasPartes extends Component
{
    #[Locked]
    public int $pruebaId;

    /** [id del apunte => nota final], como texto y con coma decimal. */
    public array $finales = [];

    public function mount(Asignatura $asignatura, Prueba $prueba)
    {
        // Solo pruebas con partes de esa asignatura, del horario abierto, y solo si es de profesor.
        abort_unless(
            $prueba->con_partes
                && $prueba->asignatura_id == $asignatura->id
                && $asignatura->horario_id == session('horario_actual_id')
                && $asignatura->horario?->esDeProfesor(),
            404
        );

        $this->pruebaId = $prueba->id;

        foreach ($prueba->notasFinales() as $apunteId => $notas) {
            $this->finales[$apunteId] = Prueba::numero($notas['final']);
        }
    }

    private function prueba(): Prueba
    {
        return Prueba::findOrFail($this->pruebaId);
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

    /** Las flechas junto a la nota final: al entero siguiente (+1) o al anterior (-1). */
    public function redondear($apunteId, int $sentido)
    {
        $final = $this->prueba()->notasFinales()[$apunteId]['final'] ?? null;
        if ($final === null) {
            return;
        }

        $this->resetValidation("finales.{$apunteId}");
        $this->retocar($apunteId, $sentido > 0 ? min(10, floor($final) + 1) : max(0, ceil($final) - 1));
    }

    /**
     * Deja $nota como nota final del alumno; null (o la misma que la media) quita el
     * retoque. Sin nota en ninguna parte no hay media que retocar.
     */
    private function retocar($apunteId, ?float $nota): void
    {
        $prueba = $this->prueba();
        $calculada = $prueba->notasFinales()[$apunteId]['calculada'] ?? null;
        $retoque = ['prueba_id' => $prueba->id, 'asignatura_alumno_id' => (int) $apunteId];

        if ($calculada === null || $nota === null || abs($nota - $calculada) < 0.005) {
            PruebaNota::where($retoque)->delete();
            $nota = $calculada;
        } else {
            // En nota va la media de la que parte el retoque: ver Prueba::notasFinales.
            PruebaNota::updateOrCreate($retoque, ['nota' => $calculada, 'nota_sobre_10' => $nota]);
        }

        $this->finales[$apunteId] = Prueba::numero($nota);
    }

    public function render()
    {
        $prueba = Prueba::with(['asignatura.modulo', 'evaluacion.curso'])->findOrFail($this->pruebaId);
        $partes = $prueba->partes()->where('estado_id', Prueba::ESTADO_ACTIVO)->get();
        $notas = $prueba->notasFinales();

        // Los alumnos activos de la asignatura (tengan nota o no) y los de baja con nota en alguna parte.
        $apuntes = AsignaturaAlumno::with('alumno')
            ->where('asignatura_id', $prueba->asignatura_id)
            ->where(fn ($q) => $q
                ->where('estado_id', AsignaturaAlumno::ESTADO_ACTIVO)
                ->orWhereIn('id', array_keys($notas)))
            ->get()
            ->sortBy(fn (AsignaturaAlumno $a) => Str::lower(Str::ascii("{$a->alumno->apellido1} {$a->alumno->apellido2} {$a->alumno->nombre}")))
            ->values();

        return view('livewire.pruebas.notas-partes', [
            'prueba'     => $prueba,
            'partes'     => $partes,
            'sumaPartes' => (float) $partes->sum('peso'),
            'apuntes'    => $apuntes,
            'notas'      => $notas,
        ]);
    }
}

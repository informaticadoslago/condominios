<?php

namespace App\Livewire\Pruebas;

use App\Models\Asignatura;
use App\Models\AsignaturaAlumno;
use App\Models\Prueba;
use App\Models\PruebaNota;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Notas de una prueba: una fila por alumno de la asignatura, con la nota sobre la
 * puntuación máxima de la prueba y la nota sobre 10. Cada casilla se guarda al salir de
 * ella. Al escribir la primera se calcula la segunda; la segunda se puede retocar a mano
 * (o con las flechas, al entero de arriba o de abajo) y se queda así hasta que se vuelva
 * a tocar la primera.
 */
class Notas extends Component
{
    #[Locked]
    public int $pruebaId;

    /** [id del apunte => ['nota' => '', 'nota10' => '']], como texto y con coma decimal. */
    public array $notas = [];

    public function mount(Asignatura $asignatura, Prueba $prueba)
    {
        // Solo pruebas de esa asignatura, del horario abierto, y solo si es de profesor.
        abort_unless(
            $prueba->asignatura_id == $asignatura->id
                && $asignatura->horario_id == session('horario_actual_id')
                && $asignatura->horario?->esDeProfesor(),
            404
        );

        $this->pruebaId = $prueba->id;
        $this->cargar();
    }

    /** Las notas guardadas, a las casillas; también tras importar (ver ImportarNotas). */
    #[On('notas-importadas')]
    public function cargar()
    {
        $this->resetValidation();
        $this->notas = [];

        foreach ($this->apuntes($this->prueba()) as $apunte) {
            $this->pintar($apunte->id, $apunte->notas->first());
        }
    }

    /**
     * Los alumnos activos de la asignatura y, además, los inactivos que ya tengan nota en
     * esta prueba; con su nota en la prueba cargada en 'notas'.
     */
    private function apuntes(Prueba $prueba)
    {
        return AsignaturaAlumno::with(['alumno', 'notas' => fn ($q) => $q->where('prueba_id', $prueba->id)])
            ->where('asignatura_id', $prueba->asignatura_id)
            ->where(fn ($q) => $q
                ->where('estado_id', AsignaturaAlumno::ESTADO_ACTIVO)
                ->orWhereHas('notas', fn ($q) => $q->where('prueba_id', $prueba->id)))
            ->get()
            ->sortBy(fn (AsignaturaAlumno $a) => mb_strtolower("{$a->alumno->apellido1} {$a->alumno->apellido2} {$a->alumno->nombre}"))
            ->values();
    }

    private function pintar(int $apunteId, ?PruebaNota $nota): void
    {
        $this->notas[$apunteId] = [
            'nota'   => Prueba::numero($nota?->nota),
            'nota10' => Prueba::numero($nota?->nota_sobre_10),
        ];
    }

    private function prueba(): Prueba
    {
        return Prueba::findOrFail($this->pruebaId);
    }

    /** El apunte, solo si es de la asignatura de la prueba. */
    private function apunteValido(Prueba $prueba, $apunteId): bool
    {
        return AsignaturaAlumno::where('asignatura_id', $prueba->asignatura_id)->whereKey($apunteId)->exists();
    }

    /** '' → null; «7,5» → 7.5; lo que no sea un número entre 0 y $maximo → false. */
    private function leerNumero(string $texto, float $maximo): float|false|null
    {
        $texto = str_replace(',', '.', trim($texto));

        if ($texto === '') {
            return null;
        }

        return is_numeric($texto) && $texto >= 0 && $texto <= $maximo ? round((float) $texto, 2) : false;
    }

    /** Se guarda la casilla que se acaba de dejar: $clave es «<apunte>.nota» o «<apunte>.nota10». */
    public function updatedNotas($valor, $clave)
    {
        [$apunteId, $campo] = explode('.', $clave) + [null, null];
        $prueba = $this->prueba();

        if (! in_array($campo, ['nota', 'nota10'], true) || ! $this->apunteValido($prueba, $apunteId)) {
            return;
        }

        $this->resetValidation("notas.{$apunteId}.nota");
        $this->resetValidation("notas.{$apunteId}.nota10");

        $maximo = $campo === 'nota' ? $prueba->puntuacion_maxima : 10;
        $numero = $this->leerNumero((string) $valor, $maximo);

        if ($numero === false) {
            $this->addError("notas.{$clave}", __('Un número entre 0 y :maximo', ['maximo' => Prueba::numero($maximo)]));

            return;
        }

        $nota = PruebaNota::where('prueba_id', $prueba->id)->where('asignatura_alumno_id', $apunteId)->first();

        if ($campo === 'nota') {
            // Tocar la nota sobre la puntuación máxima recalcula siempre la de 10.
            $datos = $numero === null
                ? null
                : ['nota' => $numero, 'nota_sobre_10' => $prueba->sobre10($numero)];
        } else {
            // La de 10 a mano: se queda como se escribe. Vacía, vuelve a la calculada (o
            // desaparece la nota entera si tampoco hay nota sobre la puntuación máxima).
            $datos = match (true) {
                $numero !== null    => ['nota' => $nota?->nota, 'nota_sobre_10' => $numero],
                $nota?->nota !== null => ['nota' => $nota->nota, 'nota_sobre_10' => $prueba->sobre10($nota->nota)],
                default             => null,
            };
        }

        $this->guardar($prueba, (int) $apunteId, $nota, $datos);
    }

    /** Las flechas junto a la nota sobre 10: al entero siguiente (+1) o al anterior (-1). */
    public function redondear($apunteId, int $sentido)
    {
        $prueba = $this->prueba();
        $nota = PruebaNota::where('prueba_id', $prueba->id)->where('asignatura_alumno_id', $apunteId)->first();

        if (! $nota) {
            return;
        }

        $entero = $sentido > 0
            ? min(10, floor($nota->nota_sobre_10) + 1)
            : max(0, ceil($nota->nota_sobre_10) - 1);

        $this->resetValidation("notas.{$apunteId}.nota10");
        $this->guardar($prueba, (int) $apunteId, $nota, ['nota' => $nota->nota, 'nota_sobre_10' => $entero]);
    }

    private function guardar(Prueba $prueba, int $apunteId, ?PruebaNota $nota, ?array $datos): void
    {
        if ($datos === null) {
            $nota?->delete();
            $nota = null;
        } elseif ($nota) {
            $nota->update($datos);
        } else {
            $nota = PruebaNota::create($datos + ['prueba_id' => $prueba->id, 'asignatura_alumno_id' => $apunteId]);
        }

        $this->pintar($apunteId, $nota);
    }

    public function render()
    {
        $prueba = Prueba::with(['asignatura.modulo', 'evaluacion.curso'])->findOrFail($this->pruebaId);

        return view('livewire.pruebas.notas', [
            'prueba'  => $prueba,
            'apuntes' => $this->apuntes($prueba),
        ]);
    }
}

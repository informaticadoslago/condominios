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
 *
 * En una prueba que no es numérica (Apto / No apto, Entregado / No entregado) no hay
 * casillas: se elige la palabra, y por dentro se guarda 1 o 0 (y 10 o 0 sobre 10).
 */
class Notas extends Component
{
    #[Locked]
    public int $pruebaId;

    /**
     * [id del apunte => ['nota' => '', 'nota10' => '', 'valor' => '']]: las notas como texto y
     * con coma decimal; 'valor' es lo elegido en una prueba que no es numérica: '1', '0' o ''
     * (sin calificar) en Apto / No apto, y marcado o no en Entregado / No entregado.
     */
    public array $notas = [];

    /** Si la prueba es de Entregado / No entregado: ver pintar. */
    #[Locked]
    public bool $deEntrega = false;

    public function mount(Asignatura $asignatura, Prueba $prueba)
    {
        // Solo pruebas de esa asignatura, del horario abierto, y solo si es de profesor. Las
        // pruebas con partes no tienen notas propias: las suyas se ven en NotasPartes.
        abort_unless(
            ! $prueba->con_partes
                && $prueba->asignatura_id == $asignatura->id
                && $asignatura->horario_id == session('horario_actual_id')
                && $asignatura->horario?->esDeProfesor(),
            404
        );

        $this->pruebaId = $prueba->id;
        $this->deEntrega = $prueba->esDeEntrega();
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
            'valor'  => $this->deEntrega
                ? (float) $nota?->nota > 0
                : ($nota?->nota === null ? '' : ($nota->nota > 0 ? '1' : '0')),
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

    /**
     * Se guarda la casilla que se acaba de dejar: $clave es «<apunte>.nota» o «<apunte>.nota10»
     * y, en una prueba que no es numérica, «<apunte>.valor».
     */
    public function updatedNotas($valor, $clave)
    {
        [$apunteId, $campo] = explode('.', $clave) + [null, null];
        $prueba = $this->prueba();

        // Cada tipo de prueba, solo con sus casillas.
        $campos = $prueba->esNumerica() ? ['nota', 'nota10'] : ['valor'];
        if (! in_array($campo, $campos, true) || ! $this->apunteValido($prueba, $apunteId)) {
            return;
        }

        if ($campo === 'valor') {
            $this->guardarPalabra($prueba, (int) $apunteId, $valor);

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

    /**
     * Lo elegido en una prueba que no es numérica: 1 la palabra buena, 0 la mala y nada, sin
     * calificar. En las de entrega solo hay entregado o no, y el «no» es no tener nota: así
     * la prueba en la que nadie ha entregado nada sigue sin notas.
     */
    private function guardarPalabra(Prueba $prueba, int $apunteId, $valor): void
    {
        $buena = $valor === true || $valor === 1 || $valor === '1';
        $mala = ! $prueba->esDeEntrega() && ($valor === 0 || $valor === '0');

        $nota = PruebaNota::where('prueba_id', $prueba->id)->where('asignatura_alumno_id', $apunteId)->first();
        $datos = match (true) {
            $buena  => ['nota' => 1, 'nota_sobre_10' => 10],
            $mala   => ['nota' => 0, 'nota_sobre_10' => 0],
            default => null,
        };

        $this->guardar($prueba, $apunteId, $nota, $datos);
    }

    /** Las flechas junto a la nota sobre 10: al entero siguiente (+1) o al anterior (-1). */
    public function redondear($apunteId, int $sentido)
    {
        $prueba = $this->prueba();
        $nota = PruebaNota::where('prueba_id', $prueba->id)->where('asignatura_alumno_id', $apunteId)->first();

        if (! $nota || ! $prueba->esNumerica()) {
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

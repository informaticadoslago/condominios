<?php

namespace App\Livewire\Pruebas;

use App\Models\AsignaturaAlumno;
use App\Models\Prueba;
use App\Models\PruebaNota;
use App\Services\Horarios\LectorNotasCsv;
use App\Services\Horarios\LectorNotasListado;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Importa a una prueba las notas pegadas como texto: un CSV (ver LectorNotasCsv) o un
 * listado copiado de la pantalla (ver LectorNotasListado), del que se elige la columna.
 * Cada alumno se coteja con los de la asignatura por nombre y apellidos (ver buscar). En
 * dos pasos: primero se revisa lo que se ha entendido y solo al confirmar se guarda.
 */
class ImportarNotas extends Component
{
    #[Locked]
    public int $pruebaId;

    public bool $abrir = false;

    public string $texto = '';

    /** Lo leído del texto, una fila por línea del CSV; vacío mientras no se ha revisado. */
    public array $filas = [];

    public bool $revisado = false;

    /** Solo con el listado copiado de la pantalla: [número de columna => nombre] de las que traen notas. */
    public array $columnas = [];

    /** La columna del listado de la que se toman las notas. */
    public ?int $columna = null;

    #[On('abrir-importar-notas')]
    public function abrirModal()
    {
        $this->reset(['texto', 'filas', 'revisado', 'columnas', 'columna']);
        $this->resetValidation();
        $this->abrir = true;
    }

    public function revisar()
    {
        $this->resetValidation();
        $leido = $this->leer();

        if ($leido === null) {
            $this->addError('texto', __('No se ha encontrado ningún alumno con nota en el texto.'));

            return;
        }

        $this->columnas = $leido['columnas'];
        $this->columna = null;
        $leido = $this->leer();
        $this->filas = $this->cotejar($leido);
        $this->revisado = true;
    }

    /** Al elegir otra columna del listado, se rehace la revisión con sus notas. */
    public function updatedColumna()
    {
        if ($leido = $this->leer()) {
            $this->columna = $leido['columna'];
            $this->filas = $this->cotejar($leido);
        }
    }

    /**
     * Lo pegado, sea el CSV o el listado copiado de la pantalla, con la misma forma: la
     * puntuación máxima y una fila por alumno con su nota. Del listado se toma la columna
     * elegida; mientras no se elige ninguna, las filas van sin nota. Null si no se entiende
     * ninguno de los dos.
     */
    private function leer(): ?array
    {
        $csv = app(LectorNotasCsv::class)->leer($this->texto);

        if ($csv['maximo'] !== null) {
            return $csv['filas'] ? $csv + ['columnas' => [], 'columna' => null] : null;
        }

        $listado = app(LectorNotasListado::class)->leer($this->texto);

        if (! $listado['columnas']) {
            return null;
        }

        // Sin columna elegida no hay notas que leer: no se adivina cuál es la de la prueba.
        $columna = $this->columna !== null && isset($listado['columnas'][$this->columna]) ? $this->columna : null;

        return [
            'maximo'   => LectorNotasListado::MAXIMO,
            'columnas' => $listado['columnas'],
            'columna'  => $columna,
            'filas'    => array_map(function (array $fila) use ($columna, $listado) {
                $nota = $columna === null ? null : ($fila['valores'][$columna] ?? null);

                return [
                    'texto'   => $fila['texto'],
                    // Para la tabla en la que se elige la columna: solo las que traen notas.
                    'valores' => array_map(fn ($c) => $fila['valores'][$c] ?? null, array_combine(array_keys($listado['columnas']), array_keys($listado['columnas']))),
                    'nota'    => $nota,
                    'error'   => match (true) {
                        $columna === null                  => null,
                        $nota === null                     => __('Sin nota'),
                        $nota > LectorNotasListado::MAXIMO => __('Nota fuera de rango'),
                        default                            => null,
                    },
                ];
            }, $listado['filas']),
        ];
    }

    /**
     * A cada fila leída le pone el apunte del alumno, la nota sobre la puntuación máxima
     * de la prueba y la nota que ya tenía. Sin alumno, o con el alumno repetido, lleva error.
     */
    private function cotejar(array $leido): array
    {
        $prueba = Prueba::findOrFail($this->pruebaId);

        $apuntes = AsignaturaAlumno::with(['alumno', 'notas' => fn ($q) => $q->where('prueba_id', $prueba->id)])
            ->where('asignatura_id', $prueba->asignatura_id)
            ->get();

        $vistos = [];

        return array_map(function (array $fila) use ($prueba, $leido, $apuntes, &$vistos) {
            $encontrados = $this->buscar($apuntes, $fila);
            $apunte = $encontrados->count() === 1 ? $encontrados->first() : null;

            $fila['error'] ??= match (true) {
                $encontrados->isEmpty()                => __('No está en la asignatura'),
                ! $apunte                              => __('Hay más de un alumno con ese nombre'),
                isset($vistos[$apunte->id])            => __('Repetido en el texto'),
                default                                => null,
            };
            if ($apunte) {
                $vistos[$apunte->id] = true;
            }

            return $fila + [
                'alumno'      => $apunte?->alumno->nombreCompleto ?? $fila['texto'] ?? "{$fila['apellidos']}, {$fila['nombre']}",
                'apunte_id'   => $apunte?->id,
                'nota_prueba' => $fila['nota'] === null ? null : round($fila['nota'] * $prueba->puntuacion_maxima / $leido['maximo'], 2),
                'nota_actual' => $apunte?->notas->first()?->nota,
            ];
        }, $leido['filas']);
    }

    /**
     * Los apuntes cuyo alumno es el de la fila, sin distinguir mayúsculas ni acentos. Del
     * CSV, con los apellidos juntos y el nombre iguales. Del listado, cuya línea es «Nombre
     * Apellidos» con hasta dos iniciales pegadas delante: la línea tiene que acabar en el
     * nombre completo del alumno y sobrarle como mucho esas dos letras; si alguno coincide
     * sin que sobre nada, es ese.
     */
    private function buscar(Collection $apuntes, array $fila): Collection
    {
        if (! isset($fila['texto'])) {
            $clave = $this->limpiar($fila['apellidos']).'|'.$this->limpiar($fila['nombre']);

            return $apuntes->filter(fn (AsignaturaAlumno $a) => $clave === $this->limpiar("{$a->alumno->apellido1} {$a->alumno->apellido2}").'|'.$this->limpiar($a->alumno->nombre))->values();
        }

        $texto = $this->limpiar($fila['texto']);
        $sobra = fn (AsignaturaAlumno $a) => ($nombre = $this->limpiar("{$a->alumno->nombre} {$a->alumno->apellido1} {$a->alumno->apellido2}")) !== '' && str_ends_with($texto, $nombre)
            ? strlen($texto) - strlen($nombre)
            : null;

        $exactos = $apuntes->filter(fn (AsignaturaAlumno $a) => $sobra($a) === 0)->values();

        return $exactos->isNotEmpty()
            ? $exactos
            : $apuntes->filter(fn (AsignaturaAlumno $a) => $sobra($a) !== null && $sobra($a) <= 2)->values();
    }

    private function limpiar(string $texto): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii($texto))));
    }

    /** De la revisión al cuadro de texto, para corregir lo pegado. */
    public function volver()
    {
        $this->revisado = false;
    }

    public function importar()
    {
        // Se vuelve a leer el texto: lo que se guarda no sale de lo que mandó el navegador.
        $leido = $this->leer();
        $filas = $leido && ! $this->faltaColumna() ? array_filter($this->cotejar($leido), fn (array $fila) => $fila['error'] === null) : [];

        if (! $filas) {
            return;
        }

        $prueba = Prueba::findOrFail($this->pruebaId);

        DB::transaction(function () use ($filas, $prueba) {
            foreach ($filas as $fila) {
                PruebaNota::updateOrCreate(
                    ['prueba_id' => $prueba->id, 'asignatura_alumno_id' => $fila['apunte_id']],
                    ['nota' => $fila['nota_prueba'], 'nota_sobre_10' => $prueba->sobre10($fila['nota_prueba'])]
                );
            }
        });

        $this->dispatch('toast-success', ['title' => __(':total notas importadas', ['total' => count($filas)])]);
        $this->dispatch('notas-importadas');
        $this->cerrar();
    }

    /** Con el listado, mientras no se elige la columna de la prueba no hay nada que importar. */
    private function faltaColumna(): bool
    {
        return $this->columnas && $this->columna === null;
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.pruebas.importar-notas', [
            'prueba'  => Prueba::findOrFail($this->pruebaId),
            'validos' => $this->faltaColumna() ? 0 : count(array_filter($this->filas, fn (array $fila) => $fila['error'] === null)),
        ]);
    }
}

<?php

namespace App\Livewire\Alumnos;

use App\Models\Alumno;
use App\Models\AsignaturaAlumno;
use App\Services\Horarios\LectorAlumnosTexto;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Importa a una asignatura los alumnos de un listado pegado como texto (ver
 * LectorAlumnosTexto). En dos pasos: primero se revisa lo que se ha entendido y solo al
 * confirmar se guarda.
 */
class Importar extends Component
{
    #[Locked]
    public int $asignaturaId;

    public bool $abrir = false;

    public string $texto = '';

    /** Lo leído del texto, una fila por alumno; vacío mientras no se ha revisado. */
    public array $filas = [];

    public bool $revisado = false;

    #[On('abrir-importar-alumnos')]
    public function abrirModal()
    {
        $this->reset(['texto', 'filas', 'revisado']);
        $this->resetValidation();
        $this->abrir = true;
    }

    public function revisar(LectorAlumnosTexto $lector)
    {
        $this->resetValidation();
        $this->filas = $lector->leer($this->texto);

        if (! $this->filas) {
            $this->addError('texto', __('No se ha encontrado ningún alumno en el texto.'));

            return;
        }

        $this->revisado = true;
    }

    /** De la revisión al cuadro de texto, para corregir lo pegado. */
    public function volver()
    {
        $this->revisado = false;
    }

    public function importar(LectorAlumnosTexto $lector)
    {
        // Se vuelve a leer el texto: lo que se guarda no sale de lo que mandó el navegador.
        $filas = array_filter($lector->leer($this->texto), fn (array $fila) => $fila['error'] === null);
        $horarioId = (int) session('horario_actual_id');
        $creados = $anadidos = $saltados = 0;

        DB::transaction(function () use ($filas, $horarioId, &$creados, &$anadidos, &$saltados) {
            foreach ($filas as $fila) {
                // Con los cuatro datos iguales es el mismo alumno.
                $alumno = Alumno::buscarIgual($horarioId, $fila['nombre'], $fila['apellido1'], $fila['apellido2'], $fila['fecha_nacimiento']);

                if ($alumno && AsignaturaAlumno::where('asignatura_id', $this->asignaturaId)->where('alumno_id', $alumno->id)->exists()) {
                    $saltados++;

                    continue;
                }

                $alumno ? $anadidos++ : $creados++;
                $alumno ??= Alumno::create([
                    'horario_id'       => $horarioId,
                    'nombre'           => $fila['nombre'],
                    'apellido1'        => $fila['apellido1'],
                    'apellido2'        => $fila['apellido2'],
                    'fecha_nacimiento' => $fila['fecha_nacimiento'],
                ]);

                AsignaturaAlumno::create([
                    'asignatura_id' => $this->asignaturaId,
                    'alumno_id'     => $alumno->id,
                    'grupo'         => $fila['grupo'],
                    'estado_id'     => AsignaturaAlumno::ESTADO_ACTIVO,
                ]);
            }
        });

        $this->dispatch('toast-success', ['title' => __(':creados nuevos, :anadidos que ya estaban en el horario y :saltados que ya estaban en la asignatura', [
            'creados'  => $creados,
            'anadidos' => $anadidos,
            'saltados' => $saltados,
        ])]);
        $this->dispatch('alumno-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.alumnos.importar', [
            'validos' => count(array_filter($this->filas, fn (array $fila) => $fila['error'] === null)),
        ]);
    }
}

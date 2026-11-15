<?php

namespace App\Livewire\Alumnos;

use App\Models\Alumno;
use App\Models\Asignatura;
use App\Models\AsignaturaAlumno;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Alta y modificación de un alumno en una asignatura. El alumno es del horario: si al
 * dar de alta ya existe uno con el mismo nombre, apellidos y fecha de nacimiento, es el
 * mismo alumno y solo se apunta a esta asignatura.
 */
class Formulario extends Component
{
    #[Locked]
    public int $asignaturaId;

    public bool $abrir = false;

    /** Id del apunte (AsignaturaAlumno) que se modifica; null al dar de alta. */
    public ?int $itemId = null;

    /** Alumno del horario elegido en el desplegable para rellenar sus datos (solo en el alta). */
    public $alumnoExistenteId = '';

    public string $nombre = '';
    public string $apellido1 = '';
    public string $apellido2 = '';
    public string $fecha_nacimiento = '';
    public string $grupo = '';

    protected function rules()
    {
        return [
            'nombre'           => ['required', 'string', 'max:50'],
            'apellido1'        => ['required', 'string', 'max:50'],
            'apellido2'        => ['nullable', 'string', 'max:50'],
            'fecha_nacimiento' => ['required', 'date', 'before_or_equal:today'],
            'grupo'            => ['required', 'string', 'max:10'],
        ];
    }

    protected function messages()
    {
        return [
            'required'        => 'Debe rellenar :attribute',
            'max'             => 'Máxima longitud de :attribute = :max',
            'date'            => 'La :attribute no es válida',
            'before_or_equal' => 'La :attribute no puede ser posterior a hoy',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'nombre'           => __('nombre'),
            'apellido1'        => __('primer apellido'),
            'apellido2'        => __('segundo apellido'),
            'fecha_nacimiento' => __('fecha de nacimiento'),
            'grupo'            => __('grupo'),
        ];
    }

    #[On('abrir-crear-alumno')]
    public function crear()
    {
        $this->reset(['itemId', 'alumnoExistenteId', 'nombre', 'apellido1', 'apellido2', 'fecha_nacimiento', 'grupo']);
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('alumno-editar')]
    public function editar($id)
    {
        $apunte = AsignaturaAlumno::with('alumno')->where('asignatura_id', $this->asignaturaId)->find($id);
        if (! $apunte) {
            return;
        }

        $this->reset('alumnoExistenteId');
        $this->itemId = $apunte->id;
        $this->rellenarAlumno($apunte->alumno);
        $this->grupo = $apunte->grupo;
        $this->resetValidation();
        $this->abrir = true;
    }

    /** Al elegir un alumno que ya está en el horario, se copian sus datos al formulario. */
    public function updatedAlumnoExistenteId($id)
    {
        $alumno = $id ? Alumno::where('horario_id', session('horario_actual_id'))->find($id) : null;

        if ($alumno) {
            $this->rellenarAlumno($alumno);
            $this->resetValidation();
        }
    }

    private function rellenarAlumno(Alumno $alumno): void
    {
        $this->nombre           = $alumno->nombre;
        $this->apellido1        = $alumno->apellido1;
        $this->apellido2        = $alumno->apellido2;
        $this->fecha_nacimiento = $alumno->fecha_nacimiento->toDateString();
    }

    public function guardar()
    {
        $data = $this->validate();

        $horarioId = (int) session('horario_actual_id');
        $datosAlumno = [
            'nombre'           => trim($data['nombre']),
            'apellido1'        => trim($data['apellido1']),
            'apellido2'        => trim($data['apellido2'] ?? ''),
            'fecha_nacimiento' => $data['fecha_nacimiento'],
        ];
        $grupo = mb_strtoupper(trim($data['grupo']));

        // Con los cuatro datos iguales es el mismo alumno.
        $igual = Alumno::buscarIgual($horarioId, ...array_values($datosAlumno));

        if ($this->itemId) {
            $apunte = AsignaturaAlumno::where('asignatura_id', $this->asignaturaId)->find($this->itemId);
            if (! $apunte) {
                return;
            }

            if ($igual && $igual->id !== $apunte->alumno_id) {
                $this->addError('nombre', __('Ya existe otro alumno en el horario con ese nombre, apellidos y fecha de nacimiento.'));

                return;
            }

            DB::transaction(function () use ($apunte, $datosAlumno, $grupo) {
                $apunte->alumno->update($datosAlumno);
                $apunte->update(['grupo' => $grupo]);
            });
            $this->dispatch('toast-success', ['title' => __('Alumno modificado')]);
        } else {
            if ($igual && AsignaturaAlumno::where('asignatura_id', $this->asignaturaId)->where('alumno_id', $igual->id)->exists()) {
                $this->addError('nombre', __('Ese alumno ya está en esta asignatura.'));

                return;
            }

            DB::transaction(function () use ($igual, $horarioId, $datosAlumno, $grupo) {
                $alumno = $igual ?? Alumno::create(['horario_id' => $horarioId] + $datosAlumno);

                AsignaturaAlumno::create([
                    'asignatura_id' => $this->asignaturaId,
                    'alumno_id'     => $alumno->id,
                    'grupo'         => $grupo,
                    'estado_id'     => AsignaturaAlumno::ESTADO_ACTIVO,
                ]);
            });
            $this->dispatch('toast-success', ['title' => $igual ? __('Alumno añadido a la asignatura') : __('Alumno creado')]);
        }

        $this->dispatch('alumno-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        // Para el alta: los alumnos del horario que aún no están en esta asignatura.
        $delHorario = $this->abrir && ! $this->itemId
            ? Alumno::where('horario_id', session('horario_actual_id'))
                ->whereDoesntHave('apuntes', fn ($q) => $q->where('asignatura_id', $this->asignaturaId))
                ->orderBy('apellido1')->orderBy('apellido2')->orderBy('nombre')
                ->get()
            : collect();

        return view('livewire.alumnos.formulario', compact('delHorario'));
    }
}

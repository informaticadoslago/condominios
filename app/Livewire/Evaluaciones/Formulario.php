<?php

namespace App\Livewire\Evaluaciones;

use App\Models\Curso;
use App\Models\Evaluacion;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    #[Locked]
    public int $cursoId;

    public bool $abrir = false;
    public ?int $itemId = null;

    public string $nombre = '';
    public string $fecha_inicio = '';
    public string $fecha_fin = '';

    protected function rules()
    {
        return [
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('evaluaciones', 'nombre')
                    ->where('curso_id', $this->cursoId)
                    ->ignore($this->itemId),
            ],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['required', 'date', 'after:fecha_inicio'],
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima longitud de :attribute = :max',
            'unique'   => 'Ya existe una evaluación con ese nombre en el curso',
            'date'     => 'La :attribute no es válida',
            'after'    => 'La fecha de fin debe ser posterior a la de inicio',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'nombre'       => __('nombre'),
            'fecha_inicio' => __('fecha de inicio'),
            'fecha_fin'    => __('fecha de fin'),
        ];
    }

    #[On('abrir-crear-evaluacion')]
    public function crear()
    {
        $this->reset(['itemId', 'nombre', 'fecha_inicio', 'fecha_fin']);
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('evaluacion-editar')]
    public function editar($id)
    {
        $item = Evaluacion::where('curso_id', $this->cursoId)->find($id);
        if (! $item) {
            return;
        }
        $this->itemId = $item->id;
        $this->nombre = $item->nombre;
        $this->fecha_inicio = $item->fecha_inicio->toDateString();
        $this->fecha_fin = $item->fecha_fin->toDateString();
        $this->resetValidation();
        $this->abrir = true;
    }

    public function guardar()
    {
        $data = $this->validate();
        $data['nombre'] = trim($data['nombre']);
        $curso = Curso::where('horario_id', session('horario_actual_id'))->find($this->cursoId);

        if (! $curso || ($this->itemId && ! Evaluacion::where('curso_id', $curso->id)->whereKey($this->itemId)->exists())) {
            return;
        }

        if ($data['fecha_inicio'] < $curso->fecha_inicio->toDateString() || $data['fecha_fin'] > $curso->fecha_fin->toDateString()) {
            $this->addError('fecha_inicio', __('Las fechas deben estar dentro de las del curso (:inicio – :fin).', [
                'inicio' => $curso->fecha_inicio->format('d/m/Y'),
                'fin'    => $curso->fecha_fin->format('d/m/Y'),
            ]));

            return;
        }

        if (Evaluacion::seSolapa($curso->id, $data['fecha_inicio'], $data['fecha_fin'], $this->itemId)) {
            $this->addError('fecha_inicio', __('Las fechas se solapan con las de otra evaluación del curso.'));

            return;
        }

        if ($this->itemId) {
            Evaluacion::whereKey($this->itemId)->update($data);
            $this->dispatch('toast-success', ['title' => __('Evaluación modificada')]);
        } else {
            Evaluacion::create($data + ['curso_id' => $curso->id, 'estado_id' => Evaluacion::ESTADO_ACTIVO]);
            $this->dispatch('toast-success', ['title' => __('Evaluación creada')]);
        }

        $this->dispatch('evaluacion-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.evaluaciones.formulario');
    }
}

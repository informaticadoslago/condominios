<?php

namespace App\Livewire\Cursos;

use App\Models\Curso;
use App\Models\Evaluacion;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
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
                Rule::unique('cursos', 'nombre')
                    ->where('horario_id', session('horario_actual_id'))
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
            'unique'   => 'Ya existe un curso con ese nombre',
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

    #[On('abrir-crear-curso')]
    public function crear()
    {
        $this->reset(['itemId', 'nombre', 'fecha_inicio', 'fecha_fin']);
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('curso-editar')]
    public function editar($id)
    {
        $item = Curso::where('horario_id', session('horario_actual_id'))->find($id);
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
        $horarioId = (int) session('horario_actual_id');

        if ($this->itemId && ! Curso::where('horario_id', $horarioId)->whereKey($this->itemId)->exists()) {
            return;
        }

        // Entre dos fechas solo puede haber un curso.
        if (Curso::seSolapa($horarioId, $data['fecha_inicio'], $data['fecha_fin'], $this->itemId)) {
            $this->addError('fecha_inicio', __('Las fechas se solapan con las de otro curso.'));

            return;
        }

        // Las evaluaciones que ya tiene no pueden quedarse fuera de las fechas nuevas.
        if ($this->itemId && Evaluacion::where('curso_id', $this->itemId)
            ->where(fn ($q) => $q
                ->whereDate('fecha_inicio', '<', $data['fecha_inicio'])
                ->orWhereDate('fecha_fin', '>', $data['fecha_fin']))
            ->exists()) {
            $this->addError('fecha_inicio', __('El curso tiene evaluaciones fuera de esas fechas.'));

            return;
        }

        if ($this->itemId) {
            Curso::whereKey($this->itemId)->update($data);
            $this->dispatch('toast-success', ['title' => __('Curso modificado')]);
        } else {
            Curso::create($data + ['horario_id' => $horarioId, 'estado_id' => Curso::ESTADO_ACTIVO]);
            $this->dispatch('toast-success', ['title' => __('Curso creado')]);
        }

        $this->dispatch('curso-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.cursos.formulario');
    }
}

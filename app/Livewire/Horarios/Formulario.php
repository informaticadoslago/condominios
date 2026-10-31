<?php

namespace App\Livewire\Horarios;

use App\Models\Horario;
use App\Models\TipoHorario;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public bool $abrir = false;
    public ?int $itemId = null;

    public string $nombre = '';
    public $tipo_horario_id = TipoHorario::ALUMNO;

    protected function rules()
    {
        return [
            'nombre' => ['required', 'string', 'max:100', Rule::unique('horarios', 'nombre')->ignore($this->itemId)],
            // Al cambiarlo no se pierde nada: las horas a mano de las sesiones (solo de
            // profesor) se quedan guardadas y vuelven a usarse si se vuelve a profesor.
            'tipo_horario_id' => [
                'required',
                Rule::exists('tipo_horarios', 'id')->where(fn ($q) => $q
                    ->where('estado_id', TipoHorario::ESTADO_ACTIVO)
                    ->orWhere('id', Horario::whereKey($this->itemId)->value('tipo_horario_id'))),
            ],
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima longitud de :attribute = :max',
            'unique'   => 'Ya existe un horario con ese nombre',
            'exists'   => 'El tipo de horario no es válido',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'nombre'          => __('nombre'),
            'tipo_horario_id' => __('tipo'),
        ];
    }

    #[On('abrir-crear-horario')]
    public function crear()
    {
        $this->reset(['itemId', 'nombre', 'tipo_horario_id']);
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('horario-editar')]
    public function editar($id)
    {
        $item = Horario::find($id);
        if (! $item) {
            return;
        }
        $this->itemId = $item->id;
        $this->nombre = $item->nombre;
        $this->tipo_horario_id = $item->tipo_horario_id;
        $this->resetValidation();
        $this->abrir = true;
    }

    public function guardar()
    {
        $data = $this->validate();

        if ($this->itemId) {
            Horario::whereKey($this->itemId)->update($data);
            $this->dispatch('toast-success', ['title' => __('Horario modificado')]);
        } else {
            Horario::create($data);
            $this->dispatch('toast-success', ['title' => __('Horario creado')]);
        }

        $this->dispatch('horario-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.horarios.formulario', [
            'tipos' => TipoHorario::query()
                ->where(fn ($q) => $q->activo()->orWhere('id', $this->tipo_horario_id))
                ->orderBy('id')
                ->pluck('descripcion', 'id'),
        ]);
    }
}

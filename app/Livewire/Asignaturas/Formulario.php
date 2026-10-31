<?php

namespace App\Livewire\Asignaturas;

use App\Models\Asignatura;
use App\Models\Modulo;
use App\Rules\NombreAsignaturaUnico;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public bool $abrir = false;
    public ?int $itemId = null;

    public string $nombre = '';
    public $modulo_id = '';
    public string $aula = '';

    protected function rules()
    {
        return [
            'nombre' => [
                'required', 'string', 'max:100',
                new NombreAsignaturaUnico(session('horario_actual_id'), $this->modulo_id ?: null, $this->itemId),
            ],
            'modulo_id' => [
                'nullable',
                Rule::exists('modulos', 'id')->where('horario_id', session('horario_actual_id')),
            ],
            'aula' => ['nullable', 'string', 'max:10'],
        ];
    }

    protected function messages()
    {
        return [
            'required'  => 'Debe rellenar :attribute',
            'max'       => 'Máxima longitud de :attribute = :max',
            'exists'    => 'El módulo no es válido',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'nombre'    => __('nombre'),
            'modulo_id' => __('módulo'),
            'aula'      => __('aula'),
        ];
    }

    #[On('abrir-crear-asignatura')]
    public function crear()
    {
        $this->reset(['itemId', 'nombre', 'modulo_id', 'aula']);
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('asignatura-editar')]
    public function editar($id)
    {
        $item = Asignatura::where('horario_id', session('horario_actual_id'))->find($id);
        if (! $item) {
            return;
        }
        $this->itemId = $item->id;
        $this->nombre = $item->nombre;
        $this->modulo_id = $item->modulo_id ?? '';
        $this->aula = $item->aula ?? '';
        $this->resetValidation();
        $this->abrir = true;
    }

    public function guardar()
    {
        $data = $this->validate();
        $data['nombre'] = trim($data['nombre']);
        $data['modulo_id'] = $data['modulo_id'] ?: null;
        $data['aula'] = trim($data['aula']) !== '' ? mb_strtoupper(trim($data['aula'])) : null;

        if ($this->itemId) {
            Asignatura::whereKey($this->itemId)->update($data);
            $this->dispatch('toast-success', ['title' => __('Asignatura modificada')]);
        } else {
            $data['horario_id'] = session('horario_actual_id');
            Asignatura::create($data);
            $this->dispatch('toast-success', ['title' => __('Asignatura creada')]);
        }

        $this->dispatch('asignatura-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.asignaturas.formulario', [
            'modulos' => Modulo::where('horario_id', session('horario_actual_id'))->orderBy('nombre')->get(),
        ]);
    }
}

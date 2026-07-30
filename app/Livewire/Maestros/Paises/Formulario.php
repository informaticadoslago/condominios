<?php
namespace App\Livewire\Maestros\Paises;

use App\Models\Pais;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public bool $abrir = false;
    public ?int $itemId = null;

    public string $nombre   = '';
    public ?string $codigo1 = null;
    public ?string $codigo2 = null;
    public string $grupo    = Pais::GRUPO_OTRO;
    public int $orden       = 1;

    protected function rules()
    {
        return [
            'nombre'  => ['required', 'string', 'max:50'],
            'codigo1' => ['nullable', 'string', 'max:2', Rule::unique('paises', 'codigo1')->ignore($this->itemId)],
            'codigo2' => ['nullable', 'string', 'max:3'],
            'grupo'   => ['required', Rule::in([Pais::GRUPO_UE, Pais::GRUPO_OTRO])],
            'orden'   => ['required', 'integer', 'min:0', 'max:127'],
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima longitud de :attribute = :max',
            'integer'  => ':attribute debe ser un número',
            'unique'   => 'Ya existe un país con ese código',
            'in'       => 'Grupo no válido',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'nombre'  => __('nombre'),
            'codigo1' => __('código ISO'),
            'codigo2' => __('código ISO (3)'),
            'grupo'   => __('grupo'),
            'orden'   => __('orden'),
        ];
    }

    #[On('abrir-crear-pais')]
    public function crear()
    {
        $this->reset(['itemId', 'nombre', 'codigo1', 'codigo2', 'grupo', 'orden']);
        $this->grupo = Pais::GRUPO_OTRO;
        $this->orden = 1;
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('pais-editar')]
    public function editar($id)
    {
        $item = Pais::find($id);
        if (! $item) {
            return;
        }
        $this->itemId  = $item->id;
        $this->nombre  = $item->nombre;
        $this->codigo1 = $item->codigo1;
        $this->codigo2 = $item->codigo2;
        $this->grupo   = $item->grupo;
        $this->orden   = $item->orden;
        $this->resetValidation();
        $this->abrir = true;
    }

    public function guardar()
    {
        $data = $this->validate();

        if ($this->itemId) {
            Pais::whereKey($this->itemId)->update($data);
            $this->dispatch('toast-success', ['title' => __('País modificado')]);
        } else {
            Pais::create($data + ['estado_id' => Pais::ESTADO_ACTIVO]);
            $this->dispatch('toast-success', ['title' => __('País creado')]);
        }

        $this->dispatch('pais-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.maestros.paises.formulario');
    }
}

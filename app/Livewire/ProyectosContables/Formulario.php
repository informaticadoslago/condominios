<?php

namespace App\Livewire\ProyectosContables;

use App\Livewire\Traits\ConEmpresaContableActiva;
use App\Models\ProyectoContable;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Alta y edición manual de un proyecto, directamente en la contabilidad: la contabilidad
 * es autónoma y no depende de que alguien lo pida desde fuera con un sujeto. Nace con
 * `sujeto_tipo`/`sujeto_id` nulos —igual que un tercero dado de alta a mano—, así que no
 * choca con los que llegan enlazados desde una actividad de gestión.
 */
class Formulario extends Component
{
    use ConEmpresaContableActiva;

    public bool $abrir = false;
    public ?int $itemId = null;

    public string $nombre = '';

    protected function rules()
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'Debe rellenar :attribute',
            'max'      => 'Máxima longitud de :attribute = :max',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'nombre' => __('nombre'),
        ];
    }

    #[On('abrir-crear-proyecto-contable')]
    public function crear()
    {
        $this->reset(['itemId', 'nombre']);
        $this->resetValidation();
        $this->abrir = true;
    }

    #[On('proyecto-contable-editar')]
    public function editar($id)
    {
        $empresaContableId = $this->empresaContableActual()?->id ?? 0;

        $item = ProyectoContable::where('empresa_contable_id', $empresaContableId)->find($id);
        if (! $item) {
            return;
        }
        $this->itemId = $item->id;
        $this->nombre = $item->nombre;
        $this->resetValidation();
        $this->abrir = true;
    }

    public function guardar()
    {
        $data = $this->validate();
        $empresaContableId = $this->empresaContableActual()?->id;

        if ($this->itemId) {
            $proyecto = ProyectoContable::where('empresa_contable_id', $empresaContableId)->findOrFail($this->itemId);
            $proyecto->update($data);
            $this->dispatch('toast-success', ['title' => __('Proyecto modificado')]);
        } else {
            ProyectoContable::create($data + ['empresa_contable_id' => $empresaContableId]);
            $this->dispatch('toast-success', ['title' => __('Proyecto creado')]);
        }

        $this->dispatch('proyecto-contable-guardado');
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.proyectos-contables.formulario');
    }
}

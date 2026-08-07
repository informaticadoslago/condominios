<?php

namespace App\Livewire\Comunidades;

use App\Livewire\Forms\ComunidadForm;
use App\Models\Comunidad;
use App\Models\CuentaBancaria;
use App\Models\EntidadBancaria;
use App\Services\Comunidades\EnlaceContableComunidad;
use Livewire\Attributes\On;
use Livewire\Component;

class Formulario extends Component
{
    public bool $abrir = false;

    public ComunidadForm $formulario;

    /** Resultados del buscador de entidad bancaria (ver x-dosl.input-autocomplete). */
    public array $resultadosEntidadesBancarias = [];

    public function mount()
    {
        $this->formulario->resetForm();
    }

    /** Buscador del autocompletado de entidad bancaria: por código o nombre. */
    public function buscarEntidadesBancarias(string $q, int $limit = 8): void
    {
        $q = trim($q);

        $this->resultadosEntidadesBancarias = $q === '' ? [] : EntidadBancaria::activo()
            ->where(function ($query) use ($q) {
                $query->where('codigo', 'like', "%{$q}%")->orWhere('descripcion', 'like', "%{$q}%");
            })
            ->orderBy('descripcion')
            ->limit($limit)
            ->get()
            ->map(fn ($e) => ['valor' => $e->id, 'etiqueta' => "{$e->codigo} - {$e->descripcion}"])
            ->all();
    }

    #[On('abrir-crear-comunidad')]
    public function crear()
    {
        $this->formulario->comunidad = new Comunidad();
        $this->formulario->resetForm();
        $this->abrir = true;
    }

    #[On('comunidad-editar')]
    public function editar($id)
    {
        $comunidad = Comunidad::with('persona')->find($id);
        if (! $comunidad) {
            return;
        }

        $this->formulario->comunidad = $comunidad;
        $this->formulario->setComunidad();
        $this->abrir = true;
    }

    public function guardar()
    {
        $validated = $this->formulario->validate();

        if ($this->formulario->comunidad?->exists) {
            $this->formulario->update($validated);
            $this->dispatch('toast-success', ['title' => __('Comunidad modificada')]);
        } else {
            $this->formulario->store($validated);
            $this->dispatch('toast-success', ['title' => __('Comunidad creada')]);
        }

        $this->dispatch('comunidad-guardada');
        $this->cerrar();

        if ($id = $this->formulario->renombrar_cuenta_bancaria_id) {
            $this->preguntarRenombrarCuentaContable($id);
        }
    }

    /**
     * Le han cambiado el nombre contable a una cuenta que ya está en el plan. Allí manda
     * el contable —puede haber corregido la denominación a propósito—, así que se
     * pregunta antes de pisársela.
     */
    private function preguntarRenombrarCuentaContable(int $cuentaBancariaId): void
    {
        $cuenta = CuentaBancaria::find($cuentaBancariaId);

        if (! $cuenta) {
            return;
        }

        $this->dispatch('swalConfirm', [
            'title' => __('¿Cambiarlo también en la contabilidad?'),
            'text'  => __('La cuenta :codigo pasaría a llamarse «:nombre» en el plan de cuentas.', [
                'codigo' => $cuenta->cuenta_contable,
                'nombre' => $cuenta->nombre_contable,
            ]),
            'icon'               => 'question',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#3085d6',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, cambiarlo'),
            'cancelButtonText'   => __('Dejarlo como está'),
            'confirmCallback'    => 'renombrarCuentaContable',
            'cancelCallback'     => 'renombradoCancelado',
            'id'                 => $cuentaBancariaId,
        ]);
    }

    #[On('renombrarCuentaContable')]
    public function renombrarCuentaContable($id)
    {
        $cuenta = CuentaBancaria::with('titular')->find($id);

        if ($cuenta && app(EnlaceContableComunidad::class)->renombrarCuentaBancaria($cuenta)) {
            $this->dispatch('toast-success', ['title' => __('Cuenta renombrada en la contabilidad')]);
        }
    }

    #[On('renombradoCancelado')]
    public function renombradoCancelado($id = null)
    {
        // El nombre contable de la comunidad ya está guardado; en el plan se queda el que
        // tenía. No hay nada que deshacer.
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.comunidades.formulario');
    }
}

<?php

namespace App\Livewire\Comunidades;

use App\Exceptions\EjercicioContableInvalidoException;
use App\Exceptions\EmpresaContableInvalidaException;
use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConBajaPorEstado;
use App\Models\Comunidad;
use App\Services\Contabilidad\AbrirEjercicioContableService;
use App\Services\Contabilidad\ResolverEmpresaContableService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    use ConBajaPorEstado;

    protected function modeloBaja(): string
    {
        return Comunidad::class;
    }

    public function mount()
    {
        $this->sort      = 'id';
        $this->direction = 'desc';
    }

    #[On('comunidad-guardada')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    // ConBajaPorEstado ya ejecuta la baja/reactivación; aquí solo avisamos de que
    // el menú lateral (no reactivo) necesita recargar la página para reflejarlo.
    #[On('ejecutarBaja')]
    public function avisarTrasBaja($id)
    {
        $this->dispatch('comunidad-guardada');
    }

    #[On('ejecutarReactivar')]
    public function avisarTrasReactivar($id)
    {
        $this->dispatch('comunidad-guardada');
    }

    /**
     * Enlaza la comunidad con la empresa contable de su CIF, creándola si es la primera
     * vez. Repetirlo no crea una segunda: el CIF manda (ver ResolverEmpresaContableService).
     *
     * Son DOS pasos, no uno: dar de alta la empresa no abre ningún ejercicio, así que
     * después se le abre el del año en curso (del 1 de enero al 31 de diciembre). Sin
     * ejercicio la empresa no admite ningún asiento y el enlace no serviría de nada.
     */
    public function enlazarContabilidad($id)
    {
        $comunidad = Comunidad::find($id);

        if (! $comunidad) {
            return;
        }

        $anho = (int) now()->year;

        try {
            $empresa = DB::transaction(function () use ($comunidad, $anho) {
                $empresa = app(ResolverEmpresaContableService::class)
                    ->ejecutar((string) $comunidad->cif, (string) $comunidad->nombre);

                app(AbrirEjercicioContableService::class)
                    ->ejecutar($empresa->id, (string) $anho, "$anho-01-01", "$anho-12-31");

                $comunidad->update(['empresa_contable_id' => $empresa->id]);

                return $empresa;
            });
        } catch (EmpresaContableInvalidaException|EjercicioContableInvalidoException $e) {
            $this->dispatch('toast-error', ['title' => $e->getMessage()]);

            return;
        }

        $this->dispatch('toast-success', ['title' => $empresa->wasRecentlyCreated
            ? __('Empresa contable creada y enlazada')
            : __('Enlazada con la empresa contable de ese CIF'),
        ]);
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = Comunidad::with('estado')
            ->when($search, function ($q) use ($search) {
                $q->whereHas('persona', fn ($p) => $p
                    ->where('razon_social', 'like', "%{$search}%")
                    ->orWhere('documento_identificativo', 'like', "%{$search}%"));
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.comunidades.lista', compact('items'));
    }
}

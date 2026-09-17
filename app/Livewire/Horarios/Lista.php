<?php

namespace App\Livewire\Horarios;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConFichaInicio;
use App\Models\AccesoDirecto;
use App\Models\Horario;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Spatie\Permission\Models\Role;

class Lista extends ListaComponent
{
    use ConFichaInicio;

    public function mount()
    {
        $this->sort      = 'nombre';
        $this->direction = 'asc';
    }

    /**
     * Solo son fijables los horarios en los que este usuario puede entrar: la
     * ficha del inicio es un atajo del menú lateral, no una puerta nueva.
     */
    protected function fichaInicioPara($id): ?array
    {
        $horario = auth()->user()->horariosAccesibles()->firstWhere('id', (int) $id);

        if (! $horario) {
            return null;
        }

        return [
            'tipo'   => AccesoDirecto::TIPO_HORARIO,
            'nombre' => $horario->nombre,
            'url'    => route('horario.entrar', $horario, false),
            'icono'  => 'fa-solid fa-clock',
        ];
    }

    #[On('horario-guardado')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    public function confirmarBorrar(int $id): void
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Borrar el horario?'),
            'text'               => __('Se borra el horario, su rol de acceso y las fichas de inicio que lo señalen. No se puede deshacer.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, borrar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarBorrarHorario',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarBorrarHorario')]
    public function borrar(int $id): void
    {
        $horario = Horario::find($id);

        if (! $horario) {
            return;
        }

        DB::transaction(function () use ($horario) {
            $url = route('horario.entrar', $horario, false);

            AccesoDirecto::where('tipo', AccesoDirecto::TIPO_HORARIO)->where('url', $url)->delete();
            Role::where('name', $horario->nombreRol())->delete();
            $horario->delete();
        });

        $this->dispatch('toast-success', ['title' => __('Horario borrado')]);
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = Horario::when($search, fn ($q) => $q->where('nombre', 'like', "%{$search}%"))
            ->orderBy($this->sort, $this->direction)
            ->orderBy('id')
            ->paginate($this->lineasXPagina);

        return view('livewire.horarios.lista', [
            'items'         => $items,
            'idsAccesibles' => auth()->user()->horariosAccesibles()->pluck('id')->all(),
        ]);
    }
}

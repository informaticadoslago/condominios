<?php

namespace App\Livewire\ConvocatoriasJuntas;

use App\Livewire\ListaComponent;
use App\Models\ConvocatoriaJunta;
use App\Models\TipoJunta;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    public function mount()
    {
        $this->sort      = 'fecha';
        $this->direction = 'desc';
    }

    #[On('convocatoria-junta-guardada')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    protected function columnasOrdenables(): ?array
    {
        return ['fecha'];
    }

    public function columnasDisponibles(): array
    {
        return [
            'fecha'   => __('Fecha'),
            'tipo'    => __('Tipo'),
            'primera' => __('Primera convocatoria'),
            'segunda' => __('Segunda convocatoria'),
            'puntos'  => __('Puntos del orden del día'),
            'carta'   => __('Fecha de la carta'),
        ];
    }

    public function definicionesFiltro(): array
    {
        return [
            [
                'clave'    => 'tipo_junta_id',
                'etiqueta' => __('Tipo'),
                'tipo'     => 'select',
                'opciones' => [0 => __('Todos')] + $this->opcionesCacheadas(
                    'tipo-juntas',
                    fn () => TipoJunta::orderBy('id')->pluck('descripcion', 'id')->all(),
                ),
                'neutro'   => 0,
                'aplicar'  => fn ($query, $valor) => $query->where('tipo_junta_id', $valor),
            ],
        ];
    }

    public function confirmarEliminar($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Eliminar convocatoria?'),
            'text'               => __('Esta acción no se puede deshacer.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, eliminar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarEliminarConvocatoriaJunta',
            'cancelCallback'     => 'eliminarConvocatoriaJuntaCancelado',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarEliminarConvocatoriaJunta')]
    public function ejecutarEliminar($id)
    {
        $convocatoria = ConvocatoriaJunta::where('comunidad_id', session('comunidad_actual_id'))->find($id);
        if ($convocatoria) {
            $convocatoria->delete();
            $this->dispatch('toast-success', ['title' => __('Convocatoria eliminada')]);
        }
    }

    #[On('eliminarConvocatoriaJuntaCancelado')]
    public function eliminarCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function render()
    {
        $items = $this->aplicarFiltros(
            ConvocatoriaJunta::with('tipoJunta')
                ->withCount('lineasOrdenDelDia')
                ->where('comunidad_id', session('comunidad_actual_id'))
        )
            ->orderBy($this->sort, $this->direction)
            ->orderBy('hora_primera_convocatoria', $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.convocatorias-juntas.lista', compact('items'));
    }
}

<?php

namespace App\Livewire\Directivos;

use App\Livewire\ListaComponent;
use App\Models\ComunidadDirectivo;
use App\Models\TipoDirectivoComunidad;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    public function mount()
    {
        // De fábrica, por puesto en el orden del catálogo (presidente primero).
        $this->sort      = 'tipo_directivo_comunidad_id';
        $this->direction = 'asc';
    }

    #[On('directivo-guardado')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    protected function columnasOrdenables(): ?array
    {
        return ['tipo_directivo_comunidad_id', 'fecha_inicio', 'fecha_fin'];
    }

    public function columnasDisponibles(): array
    {
        return [
            'puesto'       => __('Puesto'),
            'nombre'       => __('Nombre'),
            'documento'    => __('Documento'),
            'fecha_inicio' => __('Fecha de inicio'),
            'fecha_fin'    => __('Fecha de fin'),
        ];
    }

    public function definicionesFiltro(): array
    {
        return [
            [
                'clave'    => 'tipo_directivo_comunidad_id',
                'etiqueta' => __('Puesto'),
                'tipo'     => 'select',
                'opciones' => [0 => __('Todos')] + $this->opcionesCacheadas(
                    'tipo-directivo-comunidades',
                    fn () => TipoDirectivoComunidad::orderBy('id')->pluck('descripcion', 'id')->all(),
                ),
                'neutro'   => 0,
                'aplicar'  => fn ($query, $valor) => $query->where('tipo_directivo_comunidad_id', $valor),
            ],
        ];
    }

    public function confirmarEliminar($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Eliminar de la directiva?'),
            'text'               => __('La persona sigue en la comunidad; solo se quita este puesto.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, eliminar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarEliminarDirectivo',
            'cancelCallback'     => 'eliminarDirectivoCancelado',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarEliminarDirectivo')]
    public function ejecutarEliminar($id)
    {
        $directivo = ComunidadDirectivo::where('comunidad_id', session('comunidad_actual_id'))->find($id);
        if ($directivo) {
            $directivo->delete();
            $this->dispatch('toast-success', ['title' => __('Directivo eliminado')]);
        }
    }

    #[On('eliminarDirectivoCancelado')]
    public function eliminarCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function render()
    {
        $items = $this->aplicarFiltros(
            ComunidadDirectivo::with(['tipoDirectivo', 'persona'])
                ->where('comunidad_id', session('comunidad_actual_id'))
        )
            ->orderBy($this->sort, $this->direction)
            ->orderBy('fecha_inicio', 'desc')
            ->paginate($this->lineasXPagina);

        return view('livewire.directivos.lista', compact('items'));
    }
}

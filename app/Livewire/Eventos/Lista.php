<?php

namespace App\Livewire\Eventos;

use App\Livewire\ListaComponent;
use App\Models\Asignatura;
use App\Models\Evento;
use App\Models\Horario;
use App\Models\TipoEvento;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    public bool $verSuscripcion = false;
    public string $urlCalendario = '';

    public function mount()
    {
        // De fábrica, en orden cronológico: el más cercano primero.
        $this->sort      = 'fecha';
        $this->direction = 'asc';
    }

    #[On('evento-guardado')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    protected function columnasOrdenables(): ?array
    {
        return ['fecha', 'hora'];
    }

    public function columnasDisponibles(): array
    {
        return [
            'fecha'      => __('Fecha'),
            'dia'        => __('Día'),
            'hora'       => __('Hora'),
            'hora_fin'   => __('Hora fin'),
            'tipo'       => __('Tipo'),
            'asignatura' => __('Asignatura'),
            'titulo'     => __('Texto del evento'),
        ];
    }

    public function definicionesFiltro(): array
    {
        return [
            [
                // Futuros = los de hoy en adelante. Lo que no filtra es "Todos", pero de
                // fábrica se ven solo los futuros (ver filtrosPorDefecto).
                'clave'    => 'periodo',
                'etiqueta' => __('Mostrar'),
                'tipo'     => 'select',
                'opciones' => [
                    Evento::PERIODO_FUTUROS => __('Futuros'),
                    Evento::PERIODO_TODOS   => __('Todos'),
                ],
                'neutro'   => Evento::PERIODO_TODOS,
                'aplicar'  => fn ($query, $valor) => $query->where('fecha', '>=', today()->toDateString()),
            ],
            [
                // Todos los tipos, también los dados de baja: puede haber eventos suyos.
                'clave'    => 'tipo_evento_id',
                'etiqueta' => __('Tipo'),
                'tipo'     => 'select',
                'opciones' => [0 => __('Todos')] + TipoEvento::orderBy('descripcion')->pluck('descripcion', 'id')->all(),
                'neutro'   => 0,
                'aplicar'  => fn ($query, $valor) => $query->where('tipo_evento_id', $valor),
            ],
            [
                // Sin caché: las asignaturas son de cada horario y se dan de alta a menudo.
                'clave'    => 'asignatura_id',
                'etiqueta' => __('Asignatura'),
                'tipo'     => 'select',
                'opciones' => [0 => __('Todas')] + Asignatura::with('modulo')
                    ->where('horario_id', session('horario_actual_id'))
                    ->orderBy('nombre')
                    ->get()
                    ->mapWithKeys(fn (Asignatura $asignatura) => [$asignatura->id => $asignatura->nombreConModulo()])
                    ->all(),
                'neutro'   => 0,
                'aplicar'  => fn ($query, $valor) => $query->where('asignatura_id', $valor),
            ],
        ];
    }

    /** De fábrica, solo los eventos que aún no han pasado. */
    protected function filtrosPorDefecto(): array
    {
        return array_merge(parent::filtrosPorDefecto(), [
            'periodo' => Evento::PERIODO_FUTUROS,
        ]);
    }

    /** La dirección .ics de este horario, para suscribirse desde Google Calendar o el iPhone. */
    public function abrirSuscripcion()
    {
        $horario = Horario::find(session('horario_actual_id'));

        if ($horario) {
            $this->urlCalendario  = route('eventos.ics', $horario->token_calendario);
            $this->verSuscripcion = true;
        }
    }

    public function cerrarSuscripcion()
    {
        $this->verSuscripcion = false;
    }

    public function confirmarEliminar($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Eliminar el evento?'),
            'text'               => __('Se quitará del calendario.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, eliminar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarEliminarEvento',
            'cancelCallback'     => 'eliminarEventoCancelado',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarEliminarEvento')]
    public function ejecutarEliminar($id)
    {
        $evento = Evento::where('horario_id', session('horario_actual_id'))->find($id);

        if ($evento) {
            $evento->delete();
            $this->dispatch('toast-success', ['title' => __('Evento eliminado')]);
        }
    }

    #[On('eliminarEventoCancelado')]
    public function eliminarCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function render()
    {
        $items = $this->aplicarFiltros(
            Evento::with(['asignatura.modulo', 'tipoEvento'])
                ->where('horario_id', session('horario_actual_id'))
        )
            ->orderBy($this->sort, $this->direction)
            ->orderBy('fecha')
            ->orderBy('hora')
            ->orderBy('id')
            ->paginate($this->lineasXPagina);

        return view('livewire.eventos.lista', compact('items'));
    }
}

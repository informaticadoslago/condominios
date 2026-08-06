<?php

namespace App\Livewire\Propietarios;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConFiltroEstado;
use App\Livewire\Traits\ConHistorialEstadoModal;
use App\Livewire\Traits\ConSeleccionMultiple;
use App\Models\Borrador;
use App\Models\Estado;
use App\Models\Propietario;
use App\Models\Titularidad;
use App\Services\Comunidades\EnlaceContableComunidad;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    use ConFiltroEstado;
    use ConHistorialEstadoModal;
    use ConSeleccionMultiple;

    public function mount()
    {
        $this->sort      = 'id';
        $this->direction = 'desc';
    }

    public function confirmarDescartarBorrador($borradorId)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Descartar este propietario sin terminar?'),
            'text'               => __('Esta acción no se puede deshacer.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, descartar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarDescartarBorrador',
            'cancelCallback'     => 'descartarBorradorCancelado',
            'id'                 => $borradorId,
        ]);
    }

    #[On('ejecutarDescartarBorrador')]
    public function ejecutarDescartarBorrador($id)
    {
        // Si ya había creado la persona/propietario real antes de dejarlo a medias,
        // eso no se toca: descartar el borrador solo abandona lo que faltaba por rellenar.
        Borrador::delUsuario()->deTipo(Borrador::TIPO_PROPIETARIO)->whereKey($id)->delete();
    }

    #[On('descartarBorradorCancelado')]
    public function descartarBorradorCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    protected function modeloEstado(): string
    {
        return Estado::class;
    }

    protected function modeloHistorial(): string
    {
        return Propietario::class;
    }

    public function definicionesFiltro(): array
    {
        return [
            $this->filtroEstado(),
        ];
    }

    public function confirmarBaja($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Dar de baja?'),
            'text'               => __('Se marcará como inactivo.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, dar de baja'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarBaja',
            'cancelCallback'     => 'bajaCancelada',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarBaja')]
    public function ejecutarBaja($id)
    {
        $propietario = Propietario::whereKey($id)
            ->whereHas('persona', fn ($p) => $p->where('comunidad_id', session('comunidad_actual_id')))
            ->first();

        if (! $propietario) {
            return;
        }

        // No puede quedar inactivo mientras siga siendo titular vigente de algún
        // inmueble: si no, "añadir propietario" lo ocultaría pero el inmueble lo
        // seguiría teniendo como dueño actual. Hay que cerrar antes esa titularidad
        // (editando el inmueble) o transferirla a otro propietario.
        if (Titularidad::vigente()->where('propietario_id', $propietario->id)->exists()) {
            $this->dispatch('toast-error', [
                'title' => __('No se puede dar de baja: sigue siendo titular vigente de algún inmueble. Cierra o transfiere esa titularidad primero.'),
            ]);

            return;
        }

        $propietario->update(['estado_id' => Propietario::ESTADO_BAJA]);
        $this->dispatch('toast-success', ['title' => __('Propietario dado de baja')]);
    }

    public function confirmarReactivar($id)
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Reactivar?'),
            'text'               => __('Se marcará como activo.'),
            'icon'               => 'question',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#3085d6',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, reactivar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarReactivar',
            'cancelCallback'     => 'bajaCancelada',
            'id'                 => $id,
        ]);
    }

    #[On('ejecutarReactivar')]
    public function ejecutarReactivar($id)
    {
        $propietario = Propietario::whereKey($id)
            ->whereHas('persona', fn ($p) => $p->where('comunidad_id', session('comunidad_actual_id')))
            ->first();

        if ($propietario) {
            $propietario->update(['estado_id' => Propietario::ESTADO_ACTIVO]);
            $this->dispatch('toast-success', ['title' => __('Propietario reactivado')]);
        }
    }

    #[On('bajaCancelada')]
    public function bajaCancelada($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    /**
     * La consulta base (comunidad y búsqueda), SIN filtros/selección ni orden ni
     * paginación: la usan render(), invertirSeleccion() —que necesita los ids de TODO lo
     * filtrado, no solo de la página— y las acciones en lote.
     */
    private function consultaBase()
    {
        $search = trim($this->search ?? '');

        return Propietario::with(['persona', 'estado'])
            ->withCount('historialEstados')
            ->whereHas('persona', fn ($p) => $p->where('comunidad_id', session('comunidad_actual_id')))
            // Ver solo seleccionados manda también sobre la búsqueda: aunque ya no case
            // con el texto buscado, tiene que poder verse para actuar sobre él.
            ->when($search && ! $this->verSoloSeleccionados, function ($q) use ($search) {
                $q->whereHas('persona', fn ($p) => $p
                    ->buscarNombreCompleto($search)
                    ->orWhere('documento_identificativo', 'like', "%{$search}%"));
            });
    }

    /** Invierte la selección dentro de TODO lo que cumple el filtro actual (no solo la página). */
    public function invertirSeleccion(): void
    {
        $this->invertirSeleccionEn($this->consultaBase());
    }

    /**
     * Ids sobre los que actúan las acciones en lote: los marcados si hay alguno y, si no
     * hay ninguno, todo lo que cumple el filtro actual.
     */
    public function idsParaAccion(): array
    {
        if ($this->seleccionados !== []) {
            return array_values($this->seleccionados);
        }

        return $this->aplicarFiltros($this->consultaBase())
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /**
     * Da de alta en la contabilidad a los propietarios que aún no tienen subcuenta. Se
     * hace a mano porque una comunidad puede enlazarse con la contabilidad cuando ya
     * tenía propietarios dados de alta.
     */
    public function enlazarContabilidad(EnlaceContableComunidad $enlace): void
    {
        $resultado = $enlace->asignarCuentasPropietarios($this->idsParaAccion());

        $this->limpiarSeleccion();

        if ($resultado['enlazados'] === 0) {
            $this->dispatch('toast-error', ['title' => __('No se ha enlazado ningún propietario')]);

            return;
        }

        $this->dispatch('toast-success', [
            'title' => __(':enlazados propietarios enlazados', $resultado),
        ]);
    }

    public function render()
    {
        $borradores = Borrador::delUsuario()->deTipo(Borrador::TIPO_PROPIETARIO)
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn (Borrador $borrador) => ($borrador->payload['datos']['comunidad_id'] ?? null) == session('comunidad_actual_id'))
            ->map(function (Borrador $borrador) {
                $datos = $borrador->payload['datos'] ?? [];
                $borrador->nombreBorrador = trim(($datos['documento_identificativo'] ?? '').' — '.
                    ($datos['nombre'] ?? $datos['razon_social'] ?? '').' '.($datos['apellido1'] ?? ''));

                return $borrador;
            })
            ->values();

        $items = $this->aplicarSeleccion($this->consultaBase())
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        $this->sincronizarSeleccionVisible($items);

        return view('livewire.propietarios.lista', compact('items', 'borradores'));
    }
}

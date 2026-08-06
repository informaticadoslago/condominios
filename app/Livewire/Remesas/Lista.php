<?php

namespace App\Livewire\Remesas;

use App\Exceptions\RemesaNoAnulableException;
use App\Exceptions\RemesaNoGenerableException;
use App\Livewire\ListaComponent;
use App\Services\Recibos\DeshacerRemesa;
use Livewire\Attributes\On;
use App\Models\Comunidad;
use App\Models\FormaDePago;
use App\Models\Inmueble;
use App\Models\LineaRemesa;
use App\Models\Recibo;
use App\Models\Remesa;
use App\Services\Recibos\GeneradorRemesa;
use App\Services\Recibos\RegistrarCobro;
use App\Services\Recibos\RegistrarDevolucion;

/**
 * Remesas enviadas al banco. No se editan ni se borran: una remesa es constancia de un
 * envío, y una vez presentada lo que pasa después son devoluciones, no correcciones.
 *
 * Se crean en dos pasos —primero las fechas, después la lista de lo que va a entrar, por
 * si hay que dejar a alguno fuera— y desde cada una se marcan las devoluciones que va
 * mandando el banco.
 */
class Lista extends ListaComponent
{
    public bool $nuevaAbierta = false;

    /** 1 = fechas, 2 = repaso de los recibos que van a entrar. */
    public int $nuevaPaso = 1;

    public ?string $nuevaVencimiento = null;

    public ?string $nuevaFechaCargo = null;

    /** Ids de los recibos que se van a remesar; se pueden desmarcar en el paso 2. */
    public array $nuevaSeleccion = [];

    public bool $devolucionAbierta = false;

    public ?int $devolucionRemesaId = null;

    public ?string $devolucionFecha = null;

    public ?string $devolucionMotivo = null;

    /** Líneas marcadas como devueltas en el modal. */
    public array $devolucionSeleccion = [];

    public bool $cobroAbierto = false;

    public ?int $cobroRemesaId = null;

    public ?string $cobroFecha = null;

    public function mount()
    {
        $this->sort      = 'fecha_cargo';
        $this->direction = 'desc';
    }

    protected function columnasOrdenables(): ?array
    {
        return ['referencia', 'fecha_cargo'];
    }

    public function columnasDisponibles(): array
    {
        return [
            'referencia'  => __('Referencia'),
            'fecha_cargo' => __('Fecha de cargo'),
            'cuenta'      => __('Cuenta de abono'),
            'recibos'     => __('Recibos'),
            'importe'     => __('Importe'),
            'devueltos'   => __('Devueltos'),
        ];
    }

    public function definicionesFiltro(): array
    {
        return [
            [
                'clave'    => 'cargo_desde',
                'etiqueta' => __('Cargo desde'),
                'tipo'     => 'fecha',
                'aplicar'  => fn ($query, $valor) => $query->where('fecha_cargo', '>=', $valor),
            ],
            [
                'clave'    => 'cargo_hasta',
                'etiqueta' => __('Cargo hasta'),
                'tipo'     => 'fecha',
                'aplicar'  => fn ($query, $valor) => $query->where('fecha_cargo', '<=', $valor),
            ],
        ];
    }

    private function consultaBase()
    {
        $search = trim($this->search ?? '');

        return Remesa::with(['cuentaBancaria'])
            ->withCount([
                'lineas',
                'lineas as lineas_devueltas_count' => fn ($q) => $q->whereNotNull('fecha_devolucion'),
                // Lo que sigue en vuelo y todavía debe algo: mientras haya, se puede dar
                // por cobrada y se pueden marcar devoluciones.
                'lineas as lineas_pendientes_count' => fn ($q) => $q->whereNull('fecha_devolucion')
                    ->whereHas('recibo', fn ($r) => $r->where('saldo', '>', 0)),
                // Con un solo cobro registrado ya no se puede deshacer: hay dinero contado.
                'lineas as lineas_cobradas_count' => fn ($q) => $q->whereHas('cobros'),
                // Lo que se puede devolver: cobrado y todavía sin devolver. Un adeudo que
                // no se ha dado por cobrado no puede rebotar, no hay dinero que deshacer.
                'lineas as lineas_devolvibles_count' => fn ($q) => $q->whereNull('fecha_devolucion')
                    ->whereHas('cobros'),
            ])
            ->withSum('lineas', 'importe')
            ->where('comunidad_id', session('comunidad_actual_id'))
            ->when($search, fn ($q) => $q->where('referencia', 'like', "%{$search}%"));
    }

    private function comunidad(): ?Comunidad
    {
        return Comunidad::find(session('comunidad_actual_id'));
    }

    /**
     * Abre el modal proponiendo el vencimiento pendiente más antiguo: es el que toca
     * cobrar, y así no hay que ir a buscarlo a la pantalla de recibos.
     */
    public function abrirNueva(): void
    {
        $this->nuevaPaso        = 1;
        $this->nuevaVencimiento = $this->primerVencimientoPendiente();
        $this->nuevaFechaCargo  = now()->addDays(3)->toDateString();
        $this->nuevaSeleccion   = [];
        $this->nuevaAbierta     = true;
    }

    /** Paso 1 → 2: se enseña lo que va a entrar, todo marcado, para poder quitar alguno. */
    public function repasarRemesa(GeneradorRemesa $generador): void
    {
        $this->validate([
            'nuevaVencimiento' => ['required', 'date'],
            'nuevaFechaCargo'  => ['required', 'date'],
        ], attributes: [
            'nuevaVencimiento' => __('Vencimiento'),
            'nuevaFechaCargo'  => __('Fecha de cargo'),
        ]);

        $comunidad = $this->comunidad();

        if (! $comunidad) {
            return;
        }

        $recibos = $generador->recibosRemesables($comunidad, $this->nuevaVencimiento);

        if ($recibos->isEmpty()) {
            $this->dispatch('toast-error', [
                'title' => __('No hay recibos domiciliados pendientes con ese vencimiento'),
            ]);

            return;
        }

        $this->nuevaSeleccion = $recibos->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->nuevaPaso      = 2;
    }

    public function volverAFechas(): void
    {
        $this->nuevaPaso = 1;
    }

    public function generar(GeneradorRemesa $generador): void
    {
        $comunidad = $this->comunidad();

        if (! $comunidad) {
            return;
        }

        if ($this->nuevaSeleccion === []) {
            $this->dispatch('toast-error', ['title' => __('No queda ningún recibo marcado')]);

            return;
        }

        try {
            $remesa = $generador->generar(
                $comunidad,
                $this->nuevaVencimiento,
                $this->nuevaFechaCargo,
                array_map('intval', $this->nuevaSeleccion),
            );
        } catch (RemesaNoGenerableException $e) {
            // Falta un mandato, o la comunidad no tiene identificador de acreedor: el
            // modal se queda abierto para poder corregir y reintentar.
            $this->dispatch('toast-error', ['title' => $e->getMessage()]);

            return;
        }

        $this->nuevaAbierta   = false;
        $this->nuevaSeleccion = [];

        $this->dispatch('toast-success', [
            'title' => __('Remesa :referencia generada con :count recibos', [
                'referencia' => $remesa->referencia,
                'count'      => $remesa->lineas()->count(),
            ]),
        ]);
    }

    /**
     * Devoluciones de una remesa. Se abre tantas veces como haga falta: el banco las va
     * mandando por tandas, y cada vez solo se enseña lo que sigue en vuelo.
     */
    public function abrirDevolucion(int $remesaId): void
    {
        $remesa = Remesa::where('comunidad_id', session('comunidad_actual_id'))->find($remesaId);

        if (! $remesa) {
            return;
        }

        if ($this->lineasEnVuelo($remesa->id)->isEmpty()) {
            $this->dispatch('toast-error', [
                'title' => __('No hay nada que devolver: la remesa no está dada por cobrada, o ya se devolvió todo lo cobrado'),
            ]);

            return;
        }

        $this->devolucionRemesaId  = $remesa->id;
        $this->devolucionFecha     = now()->toDateString();
        $this->devolucionMotivo    = null;
        $this->devolucionSeleccion = [];
        $this->devolucionAbierta   = true;
    }

    public function marcarDevueltos(RegistrarDevolucion $servicio): void
    {
        $this->validate([
            'devolucionFecha'     => ['required', 'date'],
            'devolucionSeleccion' => ['required', 'array', 'min:1'],
            'devolucionMotivo'    => ['nullable', 'string', 'max:100'],
        ], attributes: [
            'devolucionFecha'     => __('Fecha de la devolución'),
            'devolucionSeleccion' => __('recibos devueltos'),
        ]);

        // Solo las de esta remesa y que sigan en vuelo: entre abrir el modal y confirmar,
        // otro puede haber marcado alguna.
        $ids = $this->lineasEnVuelo($this->devolucionRemesaId)
            ->whereIn('id', array_map('intval', $this->devolucionSeleccion))
            ->pluck('id')
            ->all();

        $marcadas = $servicio->registrarVarias($ids, $this->devolucionFecha, $this->devolucionMotivo);

        $this->devolucionAbierta   = false;
        $this->devolucionSeleccion = [];

        $this->dispatch($marcadas ? 'toast-success' : 'toast-error', [
            'title' => $marcadas
                ? __(':count recibos marcados como devueltos', ['count' => $marcadas])
                : __('No se ha marcado ninguna devolución'),
        ]);
    }

    /**
     * Da por cobrado lo que el banco no ha devuelto. Se hace a mano y cuando toca: hasta
     * que pasa el plazo de devolución, un adeudo presentado todavía puede rebotar, así
     * que darlo por cobrado al enviarlo sería contar un dinero que aún puede volver.
     */
    public function abrirCobro(int $remesaId): void
    {
        $remesa = Remesa::where('comunidad_id', session('comunidad_actual_id'))->find($remesaId);

        if (! $remesa) {
            return;
        }

        if ($this->pendientesDeCobro($remesa)->isEmpty()) {
            $this->dispatch('toast-error', ['title' => __('No queda nada por cobrar en esta remesa')]);

            return;
        }

        $this->cobroRemesaId = $remesa->id;
        // La fecha del cargo, que es cuando el banco movió el dinero de verdad.
        $this->cobroFecha   = $remesa->fecha_cargo?->toDateString() ?? now()->toDateString();
        $this->cobroAbierto = true;
    }

    public function cobrarRemesa(RegistrarCobro $servicio): void
    {
        $this->validate(
            ['cobroFecha' => ['required', 'date']],
            attributes: ['cobroFecha' => __('Fecha del cobro')],
        );

        $remesa = Remesa::where('comunidad_id', session('comunidad_actual_id'))->find($this->cobroRemesaId);

        if (! $remesa) {
            return;
        }

        $cobrados = $servicio->registrarRemesa($remesa, $this->cobroFecha);

        $this->cobroAbierto = false;

        $this->dispatch($cobrados ? 'toast-success' : 'toast-error', [
            'title' => $cobrados
                ? __(':count recibos dados por cobrados', ['count' => $cobrados])
                : __('No había ningún recibo pendiente de cobro'),
        ]);
    }

    public function confirmarDeshacer(int $remesaId): void
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Deshacer la remesa?'),
            'text'               => __('Se borra la remesa y sus recibos vuelven a quedar pendientes de presentar. Solo tiene sentido si no llegaste a mandarla al banco.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, deshacer'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarDeshacer',
            'cancelCallback'     => 'deshacerCancelado',
            'id'                 => $remesaId,
        ]);
    }

    #[On('ejecutarDeshacer')]
    public function ejecutarDeshacer($id, DeshacerRemesa $servicio): void
    {
        $remesa = Remesa::where('comunidad_id', session('comunidad_actual_id'))->find($id);

        if (! $remesa) {
            return;
        }

        $referencia = $remesa->referencia;

        try {
            $recibos = $servicio->ejecutar($remesa);
        } catch (RemesaNoAnulableException $e) {
            $this->dispatch('toast-error', ['title' => $e->getMessage()]);

            return;
        }

        $this->dispatch('toast-success', [
            'title' => __('Remesa :referencia deshecha; :count recibos vuelven a estar pendientes', [
                'referencia' => $referencia,
                'count'      => $recibos,
            ]),
        ]);
    }

    #[On('deshacerCancelado')]
    public function deshacerCancelado($id = null): void
    {
        // el usuario canceló; no hacemos nada
    }

    /** Líneas en vuelo cuyo recibo todavía debe algo. */
    private function pendientesDeCobro(Remesa $remesa)
    {
        return $remesa->lineas()
            ->whereNull('fecha_devolucion')
            ->whereHas('recibo', fn ($q) => $q->where('saldo', '>', 0))
            ->with('recibo')
            ->get();
    }

    /**
     * Las que se pueden marcar como devueltas: cobradas y todavía sin devolver. Sin cobro
     * no hay devolución posible — el banco solo puede devolver dinero que llegó a cargar.
     */
    private function lineasEnVuelo(?int $remesaId)
    {
        return $this->lineasDeRemesa($remesaId)
            ->whereNull('fecha_devolucion')
            ->filter(fn ($linea) => $linea->cobros_count > 0);
    }

    /**
     * TODAS las líneas de la remesa, devueltas incluidas: en el modal siguen a la vista
     * —con su fecha y su motivo— para saber qué se marcó en las tandas anteriores, pero
     * sin casilla, que ya están devueltas y no se vuelven a marcar.
     */
    private function lineasDeRemesa(?int $remesaId)
    {
        if (! $remesaId) {
            return collect();
        }

        return LineaRemesa::where('remesa_id', $remesaId)
            ->withCount('cobros')
            ->with(['recibo.inmueble.tipoInmueble', 'recibo.propietario.persona'])
            ->orderBy('id')
            ->get();
    }

    /**
     * El vencimiento más antiguo que todavía tiene recibos domiciliados sin cobrar y sin
     * presentar. Mismo criterio que GeneradorRemesa::recibosRemesables(), salvo el
     * mandato: aquí solo se propone una fecha, y si falta algún mandato ya lo dirá el
     * generador al intentarlo.
     */
    private function primerVencimientoPendiente(): ?string
    {
        $comunidadId = session('comunidad_actual_id');

        return Recibo::query()
            ->whereIn('inmueble_id', Inmueble::where('comunidad_id', $comunidadId)->select('id'))
            ->where('forma_de_pago_id', FormaDePago::RECIBO_BANCARIO)
            ->where('saldo', '>', 0)
            ->whereDoesntHave('lineasRemesas', fn ($q) => $q->whereNull('fecha_devolucion'))
            ->min('fecha_vencimiento');
    }

    public function render(GeneradorRemesa $generador)
    {
        $items = $this->aplicarFiltros($this->consultaBase())
            ->orderBy($this->sort, $this->direction)
            ->orderBy('id')
            ->paginate($this->lineasXPagina);

        // Se recalculan al pintar y no se guardan en el componente: son modelos, y lo que
        // viaja entre peticiones son solo los ids marcados.
        $recibosAremesar = $this->nuevaAbierta && $this->nuevaPaso === 2 && $this->comunidad()
            ? $generador->recibosRemesables($this->comunidad(), $this->nuevaVencimiento)
            : collect();

        return view('livewire.remesas.lista', [
            'items'           => $items,
            'recibosAremesar' => $recibosAremesar,
            'lineasRemesa'    => $this->devolucionAbierta ? $this->lineasDeRemesa($this->devolucionRemesaId) : collect(),
        ]);
    }
}

<?php

namespace App\Livewire\Remesas;

use App\Exceptions\DevolucionNoAnulableException;
use App\Exceptions\RemesaNoAnulableException;
use App\Exceptions\RemesaNoGenerableException;
use App\Livewire\ListaComponent;
use App\Services\Recibos\DeshacerDevolucionesRemesa;
use App\Services\Recibos\DeshacerRemesa;
use Livewire\Attributes\On;
use App\Models\Comunidad;
use App\Models\FormaDePago;
use App\Models\Inmueble;
use App\Models\LineaRemesa;
use App\Models\Recibo;
use App\Models\Remesa;
use App\Models\TipoComisionBancaria;
use App\Services\Recibos\EnlazarCobrosContabilidad;
use App\Services\Recibos\EnlazarRecibosContabilidad;
use App\Services\Recibos\EnviarAvisosRecibos;
use App\Services\Recibos\GeneradorRemesa;
use App\Services\Recibos\ImportadorRemesaSepa;
use App\Services\Recibos\LeerDevolucionesSepa;
use App\Services\Recibos\RegistrarCobro;
use App\Services\Recibos\RegistrarDevolucion;
use Livewire\WithFileUploads;

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
    use WithFileUploads;

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

    /**
     * Comisión que cobra el banco por CADA devolución, que se repercute al propietario.
     * Si el banco las carga juntas en un solo apunte, aquí va el importe unitario: la
     * suma de todas cuadra con ese cargo.
     */
    public ?string $devolucionGastos = null;

    /** Líneas marcadas como devueltas en el modal. */
    public array $devolucionSeleccion = [];

    /** Fichero de devoluciones del banco (pain.002) que marca la tanda de golpe. */
    public $devolucionFichero = null;

    /** Lo que trae el fichero y no se ha podido casar con esta remesa. */
    public array $devolucionSinCasar = [];

    public bool $cobroAbierto = false;

    public ?int $cobroRemesaId = null;

    public ?string $cobroFecha = null;

    public bool $detalleAbierto = false;

    public ?int $detalleRemesaId = null;

    /** Alta de una remesa que se presentó al banco por otro programa, a partir de su pain.008. */
    public bool $importarAbierto = false;

    public $importarFichero = null;

    /** Resultado de ImportadorRemesaSepa::analizar(), a la espera de confirmar. */
    public ?array $importarAnalisis = null;

    public ?string $importarFechaCargo = null;

    /** Índices (dentro de candidatas) marcados para importar; empiezan todos marcados. */
    public array $importarSeleccion = [];

    public bool $avisoTransferenciaAbierto = false;

    public ?string $avisoVencimiento = null;

    /** Recibos por transferencia marcados para avisar; se pueden desmarcar. */
    public array $avisoSeleccion = [];

    /** Ids de recibo con el "+" de CC/CCO abierto, en el aviso de transferencia. */
    public array $avisoConCopia = [];

    /** CC y CCO sueltos por id de recibo; no van ligados a ningún contacto guardado. */
    public array $avisoCc = [];

    public array $avisoCco = [];

    public bool $avisoDevolucionAbierto = false;

    public ?int $avisoDevolucionRemesaId = null;

    /** Un grupo por destinatario (ver EnviarAvisosRecibos::gruposDevolucion). */
    public array $avisoDevolucionGrupos = [];

    /** Índices (dentro de avisoDevolucionGrupos) marcados para avisar; empiezan todos marcados. */
    public array $avisoDevolucionSeleccion = [];

    /** Índices con el "+" de CC/CCO abierto. */
    public array $avisoDevolucionConCopia = [];

    /** CC y CCO sueltos por índice de grupo; no van ligados a ningún contacto guardado. */
    public array $avisoDevolucionCc = [];

    public array $avisoDevolucionCco = [];

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
                // Emisión o cobros/devoluciones que todavía no tienen asiento: si no hay
                // ninguna, "Enlazar contabilidad" no aporta nada y se oculta.
                'lineas as lineas_pendientes_enlazar_count' => fn ($q) => $q->where(
                    fn ($q2) => $q2->whereHas('recibo', fn ($r) => $r->whereNull('asiento_contable'))
                        ->orWhereHas('recibo.cobros', fn ($c) => $c->whereNull('asiento_contable'))
                ),
            ])
            ->withSum('lineas', 'importe')
            ->withSum(['lineas as lineas_devueltas_importe_sum' => fn ($q) => $q->whereNotNull('fecha_devolucion')], 'importe')
            // Lo que de verdad cobró el banco por las devoluciones (comisión + IVA de la
            // ComisionBancaria tipo "devolucion" asociada), no lo que se repercute a los
            // propietarios en gestión.
            ->withSum(['lineasComisionesBancarias as comision_devolucion_sum' => fn ($q) => $q->whereHas(
                'comisionBancaria.tipoComisionBancaria',
                fn ($q2) => $q2->where('codigo', TipoComisionBancaria::DEVOLUCION),
            )], 'importe')
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
        $this->devolucionFichero   = null;
        $this->devolucionSinCasar  = [];
        // Lo que cobró el banco la última vez, que es lo que va a cobrar esta: la tarifa
        // no cambia de una tanda a otra. Se puede corregir antes de aplicar.
        $this->devolucionGastos = $this->ultimaComisionDevolucion($remesa->comunidad_id);
        $this->devolucionAbierta = true;
    }

    /** Última comisión de devolución que se registró en la comunidad, como sugerencia. */
    private function ultimaComisionDevolucion(int $comunidadId): ?string
    {
        $ultima = LineaRemesa::whereHas('remesa', fn ($q) => $q->where('comunidad_id', $comunidadId))
            ->whereNotNull('fecha_devolucion')
            ->where('gastos_devolucion', '>', 0)
            ->latest('fecha_devolucion')
            ->value('gastos_devolucion');

        return $ultima ? (string) $ultima : null;
    }

    /**
     * Marca de golpe lo que el banco dice que ha devuelto. No aplica nada: deja las
     * casillas puestas para repasarlas, porque el fichero puede traer líneas de otra
     * remesa o de adeudos que aquí ya constan devueltos.
     */
    public function cargarFicheroDevoluciones(LeerDevolucionesSepa $lector): void
    {
        $this->validate(
            ['devolucionFichero' => ['required', 'file', 'max:2048']],
            attributes: ['devolucionFichero' => __('fichero de devoluciones')]
        );

        $devoluciones = $lector->leer(
            $this->devolucionFichero->get(),
            $this->devolucionRemesaId,
        );

        $enVuelo = $this->lineasEnVuelo($this->devolucionRemesaId)->keyBy('id');

        [$casadas, $sinCasar] = $devoluciones->partition(
            fn (array $d) => $d['linea'] && $enVuelo->has($d['linea']->id)
        );

        $this->devolucionSeleccion = $casadas->pluck('linea.id')->map('strval')->all();
        $this->devolucionSinCasar  = $sinCasar->map(fn (array $d) => [
            'referencia' => $d['referencia'],
            'deudor'     => $d['deudor'],
            'importe'    => $d['importe'],
        ])->values()->all();

        // El motivo es de la tanda entera, así que solo se rellena solo cuando todas
        // vienen por lo mismo; si vienen mezcladas, lo escribe el usuario.
        $motivos = $casadas->pluck('motivo')->unique();
        $this->devolucionMotivo = $motivos->count() === 1 ? substr($motivos->first(), 0, 100) : null;

        $this->dispatch($casadas->count() ? 'toast-success' : 'toast-error', [
            'title' => $casadas->count()
                ? __(':count devoluciones marcadas del fichero', ['count' => $casadas->count()])
                : __('El fichero no trae ninguna devolución de esta remesa'),
        ]);
    }

    public function marcarDevueltos(RegistrarDevolucion $servicio): void
    {
        $this->validate([
            'devolucionFecha'     => ['required', 'date'],
            'devolucionSeleccion' => ['required', 'array', 'min:1'],
            'devolucionMotivo'    => ['nullable', 'string', 'max:100'],
            'devolucionGastos'    => ['nullable', 'numeric', 'min:0'],
        ], attributes: [
            'devolucionFecha'     => __('Fecha de la devolución'),
            'devolucionSeleccion' => __('recibos devueltos'),
            'devolucionGastos'    => __('Comisión por devolución'),
        ]);

        // Solo las de esta remesa y que sigan en vuelo: entre abrir el modal y confirmar,
        // otro puede haber marcado alguna.
        $ids = $this->lineasEnVuelo($this->devolucionRemesaId)
            ->whereIn('id', array_map('intval', $this->devolucionSeleccion))
            ->pluck('id')
            ->all();

        $marcadas = $servicio->registrarVarias(
            $ids,
            $this->devolucionFecha,
            $this->devolucionMotivo,
            (float) ($this->devolucionGastos ?? 0),
        );

        $this->devolucionAbierta   = false;
        $this->devolucionSeleccion = [];
        $this->devolucionFichero   = null;
        $this->devolucionSinCasar  = [];

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

    /**
     * Alta de una remesa ya presentada por otro programa: se sube el pain.008 que se
     * mandó al banco y se casan sus líneas con recibos nuestros por IBAN e importe.
     */
    public function abrirImportar(): void
    {
        $this->importarFichero    = null;
        $this->importarAnalisis   = null;
        $this->importarFechaCargo = null;
        $this->importarSeleccion  = [];
        $this->importarAbierto    = true;
    }

    public function analizarFichero(ImportadorRemesaSepa $servicio): void
    {
        $this->validate(
            ['importarFichero' => ['required', 'file', 'max:2048']],
            attributes: ['importarFichero' => __('fichero de la remesa')]
        );

        $comunidad = $this->comunidad();

        if (! $comunidad) {
            return;
        }

        $this->importarAnalisis = $servicio->analizar($comunidad, $this->importarFichero->get());

        if ($this->importarAnalisis['error']) {
            $this->dispatch('toast-error', ['title' => $this->importarAnalisis['error']]);

            return;
        }

        $this->importarFechaCargo = $this->importarAnalisis['fechaCargo'] ?? now()->toDateString();
        $this->importarSeleccion  = array_map('strval', array_keys($this->importarAnalisis['candidatas']));

        $this->dispatch($this->importarAnalisis['candidatas'] ? 'toast-success' : 'toast-error', [
            'title' => $this->importarAnalisis['candidatas']
                ? __(':count líneas casadas con recibos', ['count' => count($this->importarAnalisis['candidatas'])])
                : __('El fichero no trae ninguna línea que case con un recibo'),
        ]);
    }

    public function confirmarImportar(ImportadorRemesaSepa $servicio): void
    {
        $this->validate([
            'importarFechaCargo' => ['required', 'date'],
            'importarSeleccion'  => ['required', 'array', 'min:1'],
        ], attributes: [
            'importarFechaCargo' => __('Fecha de cargo'),
            'importarSeleccion'  => __('líneas a importar'),
        ]);

        $comunidad = $this->comunidad();

        if (! $comunidad || ! $this->importarAnalisis) {
            return;
        }

        $indices = array_map('intval', $this->importarSeleccion);
        $lineas  = array_values(array_intersect_key(
            $this->importarAnalisis['candidatas'],
            array_flip($indices),
        ));

        try {
            $resultado = $servicio->importar(
                $comunidad,
                $lineas,
                $this->importarFechaCargo,
                $this->importarAnalisis['referenciaOriginal'] ?? null,
            );
        } catch (RemesaNoGenerableException $e) {
            $this->dispatch('toast-error', ['title' => $e->getMessage()]);

            return;
        }

        $this->importarAbierto  = false;
        $this->importarAnalisis = null;

        $mensaje = __('Remesa :referencia importada con :count líneas', [
            'referencia' => $resultado['remesa']->referencia,
            'count'      => count($lineas),
        ]);

        if ($resultado['sinEnlazar'] > 0) {
            $mensaje .= ' — '.__(':count recibos ya cobrados no se han podido enlazar solos, tenían más de un cobro suelto: revísalos a mano', [
                'count' => $resultado['sinEnlazar'],
            ]);
        }

        $this->dispatch('toast-success', ['title' => $mensaje]);
    }

    /** Qué recibos entraron en la remesa. Solo mirar: lo presentado ya no se toca. */
    public function verDetalle(int $remesaId): void
    {
        $remesa = Remesa::where('comunidad_id', session('comunidad_actual_id'))->find($remesaId);

        if (! $remesa) {
            return;
        }

        $this->detalleRemesaId = $remesa->id;
        $this->detalleAbierto  = true;
    }

    /**
     * Avisa por correo a los propietarios incluidos en la remesa. No se manda solo al
     * generarla: el botón está para pulsarlo cuando la remesa ya se ha mandado al banco
     * de verdad, que es cuando el cargo va a ocurrir.
     */
    public function avisarRemesa(int $remesaId, EnviarAvisosRecibos $servicio): void
    {
        if (! config('recibos.enviar_email_al_enviar_remesa')) {
            return;
        }

        $remesa = Remesa::where('comunidad_id', session('comunidad_actual_id'))->find($remesaId);

        if (! $remesa) {
            return;
        }

        $resultado = $servicio->deRemesa($remesa);

        $this->avisar($resultado);
    }

    /**
     * Abre la lista de recibos por transferencia de un vencimiento, todos marcados, para
     * poder dejar fuera a quien no toque antes de mandar los avisos.
     */
    public function abrirAvisoTransferencia(): void
    {
        if (! config('recibos.enviar_email_transferencias')) {
            return;
        }

        $this->avisoVencimiento = $this->primerVencimientoTransferenciaPendiente();

        $recibos = $this->recibosPorTransferencia($this->avisoVencimiento);

        if ($recibos->isEmpty()) {
            $this->dispatch('toast-error', [
                'title' => __('No hay recibos por transferencia pendientes'),
            ]);

            return;
        }

        $this->avisoSeleccion            = $recibos->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->avisoConCopia             = [];
        $this->avisoCc                   = [];
        $this->avisoCco                  = [];
        $this->avisoTransferenciaAbierto = true;
    }

    /** Al cambiar el vencimiento en el modal, se rehace la lista con todos marcados. */
    public function updatedAvisoVencimiento(): void
    {
        $this->avisoSeleccion = $this->recibosPorTransferencia($this->avisoVencimiento)
            ->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /** Abre o cierra los campos de CC/CCO de un recibo, en el aviso de transferencia. */
    public function toggleConCopiaTransferencia(int $reciboId): void
    {
        if (in_array($reciboId, $this->avisoConCopia, true)) {
            $this->avisoConCopia = array_values(array_diff($this->avisoConCopia, [$reciboId]));
        } else {
            $this->avisoConCopia[] = $reciboId;
        }
    }

    public function enviarAvisosTransferencia(EnviarAvisosRecibos $servicio): void
    {
        $comunidad = $this->comunidad();

        if (! $comunidad) {
            return;
        }

        if ($this->avisoSeleccion === []) {
            $this->dispatch('toast-error', ['title' => __('No queda ningún recibo marcado')]);

            return;
        }

        $this->validate([
            'avisoCc.*'  => ['nullable', 'email'],
            'avisoCco.*' => ['nullable', 'email'],
        ], [], [
            'avisoCc.*'  => __('CC'),
            'avisoCco.*' => __('CCO'),
        ]);

        // Solo los que siguen saliendo en la lista: entre abrir el modal y confirmar,
        // alguno puede haberse cobrado ya.
        $recibos = $this->recibosPorTransferencia($this->avisoVencimiento)
            ->whereIn('id', array_map('intval', $this->avisoSeleccion));

        $avisados  = 0;
        $sinCorreo = 0;

        foreach ($recibos as $recibo) {
            if ($servicio->enviarTransferencia(
                $recibo,
                $comunidad,
                cc: $this->avisoCc[$recibo->id] ?? null,
                cco: $this->avisoCco[$recibo->id] ?? null,
            )) {
                $avisados++;
            } else {
                $sinCorreo++;
            }
        }

        $this->avisoTransferenciaAbierto = false;
        $this->avisoSeleccion            = [];
        $this->avisoConCopia             = [];
        $this->avisoCc                   = [];
        $this->avisoCco                  = [];

        $this->avisar(['avisados' => $avisados, 'sin_correo' => $sinCorreo]);
    }

    /** Mensaje común de los dos avisos: cuántos han salido y a cuántos no se ha podido. */
    private function avisar(array $resultado): void
    {
        if ($resultado['avisados'] === 0) {
            $this->dispatch('toast-error', [
                'title' => __('No se ha avisado a nadie: ninguno tiene dirección de correo'),
            ]);

            return;
        }

        $this->dispatch('toast-success', [
            'title' => $resultado['sin_correo'] > 0
                ? __(':avisados avisados; :sin_correo sin dirección de correo', $resultado)
                : __(':avisados avisados por correo', $resultado),
        ]);
    }

    /** Recibos por transferencia de ese vencimiento que todavía deben algo. */
    private function recibosPorTransferencia(?string $vencimiento)
    {
        if (! $vencimiento) {
            return collect();
        }

        return Recibo::query()
            ->whereIn('inmueble_id', Inmueble::where('comunidad_id', session('comunidad_actual_id'))->select('id'))
            ->where('forma_de_pago_id', FormaDePago::TRANSFERENCIA)
            ->where('fecha_vencimiento', $vencimiento)
            ->where('saldo', '>', 0)
            ->with(['propietario.persona.contactos', 'inmueble', 'avisos'])
            ->orderBy('id')
            ->get();
    }

    private function primerVencimientoTransferenciaPendiente(): ?string
    {
        return Recibo::query()
            ->whereIn('inmueble_id', Inmueble::where('comunidad_id', session('comunidad_actual_id'))->select('id'))
            ->where('forma_de_pago_id', FormaDePago::TRANSFERENCIA)
            ->where('saldo', '>', 0)
            ->min('fecha_vencimiento');
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

    /**
     * Abre el importador de comisiones bancarias (comisiones-bancarias.importar-csv, en
     * otra pantalla) asociado a esta remesa: las comisiones de devolución que se den de
     * alta en esa sesión llevan su remesa_id, para poder repartirlas luego entre sus
     * recibos devueltos.
     */
    /**
     * El importador de comisiones vive en otro componente Livewire; sin esto, la
     * columna "Devueltos" se queda con los gastos en blanco hasta recargar la página.
     */
    #[On('comision-bancaria-importada')]
    public function refrescarComisiones(): void
    {
        // el evento fuerza el re-render de la lista
    }

    /**
     * Enlaza a contabilidad lo pendiente de los recibos de esta remesa (emisión, cobros
     * y devoluciones) sin tener que ir a Recibos y filtrar por remesa: el botón está
     * aquí porque es justo después de marcar una devolución cuando hace falta.
     */
    public function enlazarContabilidadRemesa(
        int $remesaId,
        EnlazarRecibosContabilidad $enlazador,
        EnlazarCobrosContabilidad $enlazadorCobros,
    ): void {
        $remesa = Remesa::where('comunidad_id', session('comunidad_actual_id'))->find($remesaId);

        if (! $remesa) {
            return;
        }

        $ids = $remesa->lineas()->pluck('recibo_id')->all();

        $recibos = $enlazador->ejecutar($ids);
        $cobros  = $enlazadorCobros->ejecutar($ids);

        if ($recibos['enlazados'] === 0 && $cobros['enlazados'] === 0) {
            $this->dispatch('toast-error', ['title' => __('No había nada pendiente de enlazar en esta remesa')]);

            return;
        }

        $this->dispatch('toast-success', [
            'title' => __(':recibos recibos y :cobros cobros enlazados', [
                'recibos' => $recibos['enlazados'],
                'cobros'  => $cobros['enlazados'],
            ]),
        ]);
    }

    /**
     * Abre la vista previa de a quién se va a avisar de la devolución y cuánto, todos
     * marcados, para poder dejar fuera a quien no toque antes de mandar nada.
     */
    public function abrirAvisoDevolucion(int $remesaId, EnviarAvisosRecibos $servicio): void
    {
        $remesa = Remesa::where('comunidad_id', session('comunidad_actual_id'))->find($remesaId);

        if (! $remesa) {
            return;
        }

        ['grupos' => $grupos, 'sin_correo' => $sinCorreo] = $servicio->gruposDevolucion($remesa);

        if ($grupos->isEmpty()) {
            $this->dispatch('toast-error', [
                'title' => $sinCorreo > 0
                    ? __('No se puede avisar: nadie tiene dirección de correo')
                    : __('No hay ninguna devolución que avisar'),
            ]);

            return;
        }

        $this->avisoDevolucionRemesaId  = $remesa->id;
        $this->avisoDevolucionGrupos    = $grupos->all();
        $this->avisoDevolucionSeleccion = array_map('strval', array_keys($grupos->all()));
        $this->avisoDevolucionConCopia  = [];
        $this->avisoDevolucionCc        = [];
        $this->avisoDevolucionCco       = [];
        $this->avisoDevolucionAbierto   = true;
    }

    /** Abre o cierra los campos de CC/CCO de un destinatario del modal de devolución. */
    public function toggleConCopiaDevolucion(int $indice): void
    {
        if (in_array($indice, $this->avisoDevolucionConCopia, true)) {
            $this->avisoDevolucionConCopia = array_values(array_diff($this->avisoDevolucionConCopia, [$indice]));
        } else {
            $this->avisoDevolucionConCopia[] = $indice;
        }
    }

    public function enviarAvisoDevolucion(EnviarAvisosRecibos $servicio): void
    {
        $remesa = Remesa::where('comunidad_id', session('comunidad_actual_id'))->find($this->avisoDevolucionRemesaId);

        if (! $remesa) {
            return;
        }

        if ($this->avisoDevolucionSeleccion === []) {
            $this->dispatch('toast-error', ['title' => __('No queda ningún destinatario marcado')]);

            return;
        }

        $this->validate([
            'avisoDevolucionCc.*'  => ['nullable', 'email'],
            'avisoDevolucionCco.*' => ['nullable', 'email'],
        ], [], [
            'avisoDevolucionCc.*'  => __('CC'),
            'avisoDevolucionCco.*' => __('CCO'),
        ]);

        $avisados = 0;

        foreach (array_map('intval', $this->avisoDevolucionSeleccion) as $indice) {
            $grupo = $this->avisoDevolucionGrupos[$indice] ?? null;

            if (! $grupo) {
                continue;
            }

            $servicio->enviarGrupoDevolucion(
                $grupo['recibo_ids'],
                $grupo['fecha'],
                $grupo['correo'],
                $remesa->comunidad,
                cc: $this->avisoDevolucionCc[$indice] ?? null,
                cco: $this->avisoDevolucionCco[$indice] ?? null,
            );
            // Un aviso por destinatario, no por recibo: si el grupo lleva dos inmuebles,
            // sigue siendo un solo correo. Contar recibo_ids diría "2 avisados" habiendo
            // mandado uno.
            $avisados++;
        }

        $this->avisoDevolucionAbierto   = false;
        $this->avisoDevolucionGrupos    = [];
        $this->avisoDevolucionSeleccion = [];
        $this->avisoDevolucionConCopia  = [];
        $this->avisoDevolucionCc        = [];
        $this->avisoDevolucionCco       = [];

        $this->avisar(['avisados' => $avisados, 'sin_correo' => 0]);
    }

    public function abrirImportarComisionDevolucion(int $remesaId): void
    {
        $remesa = Remesa::where('comunidad_id', session('comunidad_actual_id'))->find($remesaId);

        if (! $remesa) {
            return;
        }

        $this->dispatch('abrir-importar-csv', remesaId: $remesa->id);
    }

    public function confirmarDeshacerDevoluciones(int $remesaId): void
    {
        $this->dispatch('swalConfirm', [
            'title'              => __('¿Deshacer las devoluciones?'),
            'text'               => __('Se desmarca todo lo devuelto de esta remesa: los recibos vuelven a Cobrado y se borra el asiento contable de cada devolución. Úsalo para corregir una tanda mal tecleada (fecha, motivo o comisión) y repetirla bien.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, deshacer'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'ejecutarDeshacerDevoluciones',
            'cancelCallback'     => 'deshacerDevolucionesCancelado',
            'id'                 => $remesaId,
        ]);
    }

    #[On('ejecutarDeshacerDevoluciones')]
    public function ejecutarDeshacerDevoluciones($id, DeshacerDevolucionesRemesa $servicio): void
    {
        $remesa = Remesa::where('comunidad_id', session('comunidad_actual_id'))->find($id);

        if (! $remesa) {
            return;
        }

        try {
            $count = $servicio->ejecutar($remesa);
        } catch (DevolucionNoAnulableException $e) {
            $this->dispatch('toast-error', ['title' => $e->getMessage()]);

            return;
        }

        $this->dispatch('toast-success', [
            'title' => __(':count devoluciones deshechas en la remesa :referencia', [
                'count'      => $count,
                'referencia' => $remesa->referencia,
            ]),
        ]);
    }

    #[On('deshacerDevolucionesCancelado')]
    public function deshacerDevolucionesCancelado($id = null): void
    {
        // el usuario canceló; no hacemos nada
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
            'lineasDetalle'   => $this->detalleAbierto ? $this->lineasDeRemesa($this->detalleRemesaId) : collect(),
            'remesaDetalle'   => $this->detalleAbierto ? Remesa::find($this->detalleRemesaId) : null,
            'recibosAavisar'  => $this->avisoTransferenciaAbierto
                ? $this->recibosPorTransferencia($this->avisoVencimiento)
                : collect(),
            'avisoRemesaActivo'         => (bool) config('recibos.enviar_email_al_enviar_remesa'),
            'avisoTransferenciaActivo'  => (bool) config('recibos.enviar_email_transferencias'),
        ]);
    }
}

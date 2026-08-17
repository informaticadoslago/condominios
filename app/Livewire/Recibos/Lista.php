<?php

namespace App\Livewire\Recibos;

use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConFiltroEstado;
use App\Livewire\Traits\ConHistorialEstadoModal;
use App\Livewire\Traits\ConSeleccionMultiple;
use App\Models\Comunidad;
use App\Models\FormaDePago;
use App\Models\Inmueble;
use App\Models\Recibo;
use App\Models\TipoEstadoRecibo;
use App\Services\Recibos\EnlazarCobrosContabilidad;
use App\Services\Recibos\EnlazarRecibosContabilidad;
use App\Services\Recibos\EnviarAvisosRecibos;
use App\Services\Recibos\RegistrarCobro;
use Illuminate\Support\Facades\DB;

/**
 * Los recibos no se crean ni se borran desde aquí: los vuelca GeneradorRecibos al
 * aprobar el presupuesto. Lo que sí se hace es cobrarlos: los que no van por remesa
 * (transferencia, efectivo) se marcan a mano, uno o varios de golpe.
 */
class Lista extends ListaComponent
{
    use ConFiltroEstado;
    // El título del modal lo pone esta lista: un recibo no tiene nombre, se identifica
    // por el inmueble y el vencimiento.
    use ConHistorialEstadoModal {
        verHistorial as private verHistorialBase;
    }
    use ConSeleccionMultiple;

    public bool $cobroAbierto = false;

    public ?string $cobroFecha = null;

    public int $cobroFormaDePagoId = FormaDePago::TRANSFERENCIA;

    /** Recibos sobre los que va a actuar el modal, congelados al abrirlo. */
    public array $cobroIds = [];

    public function mount()
    {
        $this->sort      = 'fecha_vencimiento';
        $this->direction = 'desc';
    }

    protected function modeloEstado(): string
    {
        return TipoEstadoRecibo::class;
    }

    protected function modeloHistorial(): string
    {
        return Recibo::class;
    }

    public function verHistorial($id): void
    {
        $this->verHistorialBase($id);

        $recibo = Recibo::with('inmueble.tipoInmueble')->find($id);

        $this->historialTitulo = $recibo
            ? trim(sprintf('%s %s %s',
                $recibo->inmueble?->tipoInmueble?->descripcion,
                $recibo->inmueble?->planta,
                $recibo->inmueble?->puerta,
            )).' · '.__('vence el :fecha', ['fecha' => $recibo->fecha_vencimiento?->format('d/m/Y')])
            : null;
    }

    /** Solo columnas de la propia tabla: las de inmueble y propietario pedirían join. */
    protected function columnasOrdenables(): ?array
    {
        return ['fecha_vencimiento', 'numero_pago', 'importe', 'importe_pagado', 'saldo'];
    }

    protected function filtroCobro(): array
    {
        return [
            'clave'    => 'cobro',
            'etiqueta' => __('Cobro'),
            'tipo'     => 'select',
            'opciones' => [
                0 => __('Todos'),
                1 => __('Pendientes'),
                2 => __('Pagados'),
            ],
            'neutro'   => 0,
            // `saldo` es columna generada por el motor: no puede desincronizarse.
            'aplicar'  => fn ($query, $valor) => (int) $valor === 1
                ? $query->where('saldo', '>', 0)
                : $query->where('saldo', '<=', 0),
        ];
    }

    /**
     * Un propietario puede tener varios inmuebles en la misma planta (el A y el B): el
     * buscador de texto no distingue entre ellos, así que planta y puerta van aparte.
     */
    protected function filtroPlanta(): array
    {
        $comunidadId = session('comunidad_actual_id');

        return [
            'clave'    => 'planta',
            'etiqueta' => __('Planta'),
            'tipo'     => 'select',
            'opciones' => [0 => __('Todas')] + $this->opcionesCacheadas(
                'recibos-plantas-'.$comunidadId,
                fn () => Inmueble::where('comunidad_id', $comunidadId)
                    ->whereNotNull('planta')
                    ->where('planta', '!=', '')
                    ->distinct()
                    ->orderBy('planta')
                    ->pluck('planta', 'planta')
                    ->all(),
            ),
            'neutro'   => 0,
            'aplicar'  => fn ($query, $valor) => $query->whereHas('inmueble', fn ($i) => $i->where('planta', $valor)),
        ];
    }

    protected function filtroPuerta(): array
    {
        $comunidadId = session('comunidad_actual_id');

        return [
            'clave'    => 'puerta',
            'etiqueta' => __('Puerta'),
            'tipo'     => 'select',
            'opciones' => [0 => __('Todas')] + $this->opcionesCacheadas(
                'recibos-puertas-'.$comunidadId,
                fn () => Inmueble::where('comunidad_id', $comunidadId)
                    ->whereNotNull('puerta')
                    ->where('puerta', '!=', '')
                    ->distinct()
                    ->orderBy('puerta')
                    ->pluck('puerta', 'puerta')
                    ->all(),
            ),
            'neutro'   => 0,
            'aplicar'  => fn ($query, $valor) => $query->whereHas('inmueble', fn ($i) => $i->where('puerta', $valor)),
        ];
    }

    /** La del recibo: la que tenía el inmueble al aprobarse el presupuesto, no la de hoy. */
    protected function filtroFormaDePago(): array
    {
        return [
            'clave'    => 'forma_de_pago_id',
            'etiqueta' => __('Forma de pago'),
            'tipo'     => 'select',
            'opciones' => [0 => __('Todas')] + $this->opcionesCacheadas(
                'formas-de-pago-activas',
                fn () => FormaDePago::activo()->orderBy('descripcion')->pluck('descripcion', 'id')->all(),
            ),
            'neutro'   => 0,
            'aplicar'  => fn ($query, $valor) => $query->where('forma_de_pago_id', $valor),
        ];
    }

    /** Para poder buscar justo lo que falta por mandar a la contabilidad. */
    protected function filtroEnlaceContable(): array
    {
        return [
            'clave'    => 'enlace_contable',
            'etiqueta' => __('Contabilidad'),
            'tipo'     => 'select',
            'opciones' => [
                0 => __('Todos'),
                1 => __('Sin enlazar'),
                2 => __('Enlazados'),
            ],
            'neutro'   => 0,
            'aplicar'  => fn ($query, $valor) => (int) $valor === 1
                ? $query->whereNull('asiento_contable')
                : $query->whereNotNull('asiento_contable'),
        ];
    }

    protected function filtroVencimientoDesde(): array
    {
        return [
            'clave'    => 'vencimiento_desde',
            'etiqueta' => __('Vence desde'),
            'tipo'     => 'fecha',
            'aplicar'  => fn ($query, $valor) => $query->where('fecha_vencimiento', '>=', $valor),
        ];
    }

    protected function filtroVencimientoHasta(): array
    {
        return [
            'clave'    => 'vencimiento_hasta',
            'etiqueta' => __('Vence hasta'),
            'tipo'     => 'fecha',
            'aplicar'  => fn ($query, $valor) => $query->where('fecha_vencimiento', '<=', $valor),
        ];
    }

    public function definicionesFiltro(): array
    {
        return [
            $this->filtroEstado(),
            $this->filtroCobro(),
            $this->filtroFormaDePago(),
            $this->filtroEnlaceContable(),
            $this->filtroPlanta(),
            $this->filtroPuerta(),
            $this->filtroVencimientoDesde(),
            $this->filtroVencimientoHasta(),
        ];
    }

    public function columnasDisponibles(): array
    {
        return [
            'inmueble'       => __('Inmueble'),
            'propietario'    => __('Propietario'),
            'presupuesto'    => __('Presupuesto'),
            'numero_pago'    => __('Pago'),
            'vencimiento'    => __('Vencimiento'),
            'importe'        => __('Importe'),
            'importe_pagado' => __('Pagado'),
            'saldo'          => __('Saldo'),
            'forma_de_pago'  => __('Forma de pago'),
            'estado'         => __('Estado'),
            'asiento'        => __('Asiento'),
        ];
    }

    /**
     * La consulta base (comunidad y búsqueda), SIN filtros/selección ni orden ni
     * paginación: la usan render(), invertirSeleccion() —que necesita los ids de TODO
     * lo filtrado, no solo de la página— y las acciones en lote.
     */
    private function consultaBase()
    {
        $search = trim($this->search ?? '');

        return Recibo::with(['inmueble.tipoInmueble', 'propietario.persona', 'presupuesto', 'formaDePago', 'estado'])
            // Para enseñar el botón del historial solo cuando hay algo más que el alta.
            ->withCount('historialEstados')
            // El recibo no guarda la comunidad: la pone el inmueble al que se le cobra.
            ->whereHas('inmueble', fn ($i) => $i->where('comunidad_id', session('comunidad_actual_id')))
            // Ver solo seleccionados manda también sobre la búsqueda: aunque un recibo ya
            // no case con el texto buscado, tiene que poder verse para actuar sobre él.
            ->when($search && ! $this->verSoloSeleccionados, function ($q) use ($search) {
                // Anidado, para que el orWhereHas no se desenganche del filtro de comunidad.
                $q->where(function ($q2) use ($search) {
                    // El inmueble se busca por lo que se ve de él en la lista: su tipo
                    // («garaje», «piso»…), la planta o la puerta.
                    $q2->whereHas('inmueble', fn ($i) => $i
                        ->where('puerta', 'like', "%{$search}%")
                        ->orWhere('planta', 'like', "%{$search}%")
                        ->orWhereHas('tipoInmueble', fn ($t) => $t->where('descripcion', 'like', "%{$search}%")))
                        ->orWhereHas('propietario.persona', fn ($p) => $p
                            ->where('nombre', 'like', "%{$search}%")
                            ->orWhere('apellido1', 'like', "%{$search}%")
                            ->orWhere('apellido2', 'like', "%{$search}%")
                            ->orWhere('razon_social', 'like', "%{$search}%"));
                });
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
     * Abre el modal con los ids ya congelados: entre abrirlo y confirmar se puede
     * cambiar de página o de filtro, y lo que se cobra tiene que ser lo que se vio.
     */
    public function abrirCobro(): void
    {
        $this->cobroIds = $this->idsParaAccion();

        if ($this->cobroIds === []) {
            $this->dispatch('toast-error', ['title' => __('No hay recibos sobre los que actuar')]);

            return;
        }

        $this->cobroFecha   = now()->toDateString();
        $this->cobroAbierto = true;
    }

    /** Cobra el pendiente completo de cada recibo; los ya pagados se quedan como están. */
    public function cobrar(RegistrarCobro $registrarCobro): void
    {
        $this->validate([
            'cobroFecha'         => ['required', 'date'],
            'cobroFormaDePagoId' => ['required', 'exists:formas_de_pago,id'],
        ], attributes: [
            'cobroFecha'         => __('Fecha del cobro'),
            'cobroFormaDePagoId' => __('Forma de pago'),
        ]);

        $cobrados = $registrarCobro->registrarVarios(
            $this->cobroIds,
            $this->cobroFecha,
            $this->cobroFormaDePagoId,
        );

        $this->cobroAbierto = false;
        $this->cobroIds     = [];
        $this->limpiarSeleccion();

        $this->dispatch('toast-success', [
            'title' => $cobrados
                ? __(':count recibos cobrados', ['count' => $cobrados])
                : __('No había ningún recibo pendiente de cobro'),
        ]);
    }

    /**
     * Vuelve a copiar del inmueble la forma de pago y la cuenta bancaria del recibo: se
     * congelan al generarlo (ver GeneradorRecibos), así que si se corrigen después en el
     * inmueble, el recibo ya emitido se queda con las de entonces. Solo tiene sentido
     * mientras el recibo sigue Generado: uno ya Enviado o Cobrado se presentó o se pagó
     * con la forma de pago que llevaba en ese momento, y cambiarla ahora la falsearía.
     */
    public function resincronizarFormaPago(int $reciboId): void
    {
        $recibo = Recibo::where('estado_id', TipoEstadoRecibo::GENERADO)
            ->whereIn('inmueble_id', Inmueble::where('comunidad_id', session('comunidad_actual_id'))->select('id'))
            ->find($reciboId);

        if (! $recibo) {
            return;
        }

        $formaPago = $recibo->inmueble?->formaPagoVigente;

        if (! $formaPago) {
            $this->dispatch('toast-error', ['title' => __('El inmueble no tiene forma de pago vigente')]);

            return;
        }

        $recibo->update([
            'forma_de_pago_id'   => $formaPago->forma_de_pago_id,
            'cuenta_bancaria_id' => $formaPago->cuenta_bancaria_id,
        ]);

        $this->dispatch('toast-success', ['title' => __('Forma de pago actualizada')]);
    }

    /**
     * Manda a la contabilidad lo que aún no ha entrado en ningún asiento: primero la
     * emisión de los recibos y después el dinero que ya han cobrado. Se hace a mano, y no
     * al aprobar el presupuesto, porque una comunidad puede enlazarse con la contabilidad
     * cuando ya tiene recibos emitidos y cobrados.
     *
     * El orden importa: un cobro cancela la deuda que dejó la emisión, así que los cobros
     * de un recibo que no esté emitido en contabilidad se quedan fuera. Enlazando la
     * emisión primero, en la misma pasada ya entran también sus cobros.
     */
    public function enlazarContabilidad(
        EnlazarRecibosContabilidad $enlazador,
        EnlazarCobrosContabilidad $enlazadorCobros,
    ): void {
        $ids = $this->idsParaAccion();

        $recibos = $enlazador->ejecutar($ids);
        $cobros  = $enlazadorCobros->ejecutar($ids);

        $this->limpiarSeleccion();

        if ($recibos['enlazados'] === 0 && $cobros['enlazados'] === 0) {
            $this->dispatch('toast-error', ['title' => __('No se ha enlazado nada')]);

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
     * Avisa por correo a los que pagan por transferencia de que les toca ingresar.
     *
     * De la selección solo se avisa a los de transferencia y con saldo pendiente: los
     * domiciliados se cobran solos y su aviso es otro (el del cargo, desde la remesa).
     * Los que se quedan fuera se cuentan y se dicen, para que no parezca que se avisó a
     * todo lo marcado.
     */
    public function avisarTransferencias(EnviarAvisosRecibos $servicio): void
    {
        if (! config('recibos.enviar_email_transferencias')) {
            return;
        }

        $comunidad = Comunidad::find(session('comunidad_actual_id'));

        if (! $comunidad) {
            return;
        }

        $porTransferencia = Recibo::whereIn('id', $this->idsParaAccion())
            ->where('forma_de_pago_id', FormaDePago::TRANSFERENCIA)
            ->get(['id', 'saldo']);

        $ids = $porTransferencia->where('saldo', '>', 0)->pluck('id')->all();

        if ($ids === []) {
            // Los dos motivos se dicen por separado: con el filtro puesto en
            // Transferencia, un «ninguno paga por transferencia» contradice lo que se
            // está viendo en pantalla y parece un fallo.
            $this->dispatch('toast-error', [
                'title' => $porTransferencia->isEmpty()
                    ? __('Ninguno de los recibos marcados paga por transferencia')
                    : __('Los :count recibos por transferencia marcados ya están cobrados', [
                        'count' => $porTransferencia->count(),
                    ]),
            ]);

            return;
        }

        $resultado = $servicio->deTransferencia($ids, $comunidad);

        $this->limpiarSeleccion();

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

    /**
     * Importe, pagado y saldo a totalizar: de lo marcado si hay selección —venga o no de
     * la página actual—, y si no de TODO lo filtrado, no solo de la página que se ve.
     *
     * @return array{importe: float, importe_pagado: float, saldo: float}
     */
    private function totales(): array
    {
        $query = $this->seleccionados !== []
            ? Recibo::whereIn('id', $this->seleccionados)
            : $this->aplicarSeleccion($this->consultaBase());

        $totales = $query->toBase()
            ->select([
                DB::raw('COALESCE(SUM(importe), 0) AS importe'),
                DB::raw('COALESCE(SUM(importe_pagado), 0) AS importe_pagado'),
                DB::raw('COALESCE(SUM(saldo), 0) AS saldo'),
            ])
            ->first();

        return [
            'importe'        => (float) $totales->importe,
            'importe_pagado' => (float) $totales->importe_pagado,
            'saldo'          => (float) $totales->saldo,
        ];
    }

    public function render()
    {
        $items = $this->aplicarSeleccion($this->consultaBase())
            ->orderBy($this->sort, $this->direction)
            // Desempate estable: todos los recibos de un mismo pago tienen la misma fecha
            // de vencimiento (y muchas veces el mismo importe y el mismo saldo), así que
            // sin esto el orden de las filas empatadas lo decide el filesort y cambia de
            // una consulta a otra: la fila que acabas de marcar salta de sitio, y entre
            // páginas unos recibos se repiten y otros no llegan a verse nunca.
            ->orderBy('id')
            ->paginate($this->lineasXPagina);

        $this->sincronizarSeleccionVisible($items);

        $formasDePago = FormaDePago::activo()->orderBy('descripcion')->pluck('descripcion', 'id');

        return view('livewire.recibos.lista', compact('items', 'formasDePago') + [
            'avisoTransferenciaActivo' => (bool) config('recibos.enviar_email_transferencias'),
            'totales'                  => $this->totales(),
        ]);
    }
}

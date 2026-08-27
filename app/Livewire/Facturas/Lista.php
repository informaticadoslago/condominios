<?php

namespace App\Livewire\Facturas;

use App\Exceptions\AsientoInvalidoException;
use App\Exceptions\CuentaContableDesconocidaException;
use App\Exceptions\EjercicioCerradoException;
use App\Exceptions\EjercicioContableDesconocidoException;
use App\Livewire\ListaComponent;
use App\Livewire\Traits\ConSeleccionMultiple;
use App\Models\Actividad;
use App\Models\Documento;
use App\Models\FacturaProveedor;
use App\Models\PagoFactura;
use App\Services\Facturas\AdjuntarSoporteFactura;
use App\Services\Facturas\AltaProveedorDesdeFactura;
use App\Services\Facturas\EnlazarFacturasContabilidad;
use App\Services\Facturas\EnlazarPagosContabilidad;
use App\Services\Facturas\LectorPdf;
use App\Services\Facturas\RegistrarPagoFactura;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;

class Lista extends ListaComponent
{
    use WithFileUploads;
    use ConSeleccionMultiple;

    /**
     * El papel que llega tarde, indexado por id de factura: el «Sin soporte» de cada fila
     * hace de clip, y el id va en el propio nombre del modelo (soporte.7) para no tener
     * que recordar aparte de qué factura era.
     */
    public array $soporte = [];

    /** ids de facturas con su histórico de pagos desplegado en la tabla. */
    public array $expandido = [];

    public bool $pagoLoteAbierto = false;

    public ?string $pagoLoteFecha = null;

    /** Si el lote sale como una única transferencia (un solo apunte en el banco) o una por factura. */
    public bool $pagoLoteUnicoApunte = false;

    /** Facturas sobre las que va a actuar el modal, congeladas al abrirlo. */
    public array $pagoLoteIds = [];

    public function mount()
    {
        $this->sort      = 'id';
        $this->direction = 'desc';
    }

    public function toggleDetalle(int $id): void
    {
        if (in_array($id, $this->expandido, true)) {
            $this->expandido = array_values(array_diff($this->expandido, [$id]));
        } else {
            $this->expandido[] = $id;
        }
    }

    #[On('factura-importada')]
    #[On('factura-pagada')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    /**
     * El papel de una factura «sin soporte» aparece más tarde: se sube desde la propia
     * lista y se le engancha como documento del proveedor, igual que si hubiera venido en
     * el alta. Solo va hacia adelante: al que ya tiene papel no se le cambia desde aquí.
     */
    public function updatedSoporte($fichero, $facturaId)
    {
        // En una lista no hay hueco para el mensaje de error debajo del campo: va en toast.
        try {
            $this->validate([
                "soporte.$facturaId" => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            ], [
                'mimes' => __('El fichero tiene que ser un PDF o una imagen'),
                'max'   => __('El fichero no puede pasar de 10 MB'),
            ]);
        } catch (ValidationException $e) {
            unset($this->soporte[$facturaId]);
            $this->dispatch('toast-error', ['title' => $e->validator->errors()->first()]);

            return;
        }

        $factura = FacturaProveedor::with('proveedor.persona')->find($facturaId);

        // El id viene del navegador: se comprueba que la factura es de esta comunidad.
        $esDeLaComunidadActual = $factura
            && $factura->proveedor->persona->comunidad_id == session('comunidad_actual_id');

        if (! $esDeLaComunidadActual || $factura->documento_id) {
            unset($this->soporte[$facturaId]);

            return;
        }

        $documento = (new AdjuntarSoporteFactura())->ejecutar(
            $factura->proveedor,
            $factura->numero_factura,
            Documento::subirFichero($fichero, enBorrador: true),
        );

        $factura->update(['documento_id' => $documento->id]);

        unset($this->soporte[$facturaId]);

        $this->dispatch('toast-success', ['title' => __('Papel adjuntado a la factura')]);
    }

    /**
     * id de la factura que se está corrigiendo ahora mismo. MarcarPlantillaFactura emite
     * "plantilla-factura-completada" con el mismo nombre tanto si la abrió esta lista como
     * si la abrió Proveedores\ResultadoFactura (incluida en esta misma página desde
     * Importar): sin esta guarda, un "indice" que en realidad es la posición dentro de un
     * lote de importación podría confundirse con el id de una factura cualquiera.
     */
    public ?int $facturaEnCorreccion = null;

    /**
     * Una factura ya importada tiene un dato mal (p.ej. la fecha, si el PDF la escribe en
     * letra y no coincidía con la plantilla): se relee el PDF ya guardado y se manda al
     * mismo asistente de marcado que usa Proveedores, pero solo para ESE campo.
     */
    public function corregirCampo($facturaId, $tipoCampo)
    {
        $factura = FacturaProveedor::with(['documento', 'proveedor.persona'])->find($facturaId);
        if (! $factura || ! $factura->documento) {
            return;
        }

        if (! $factura->documento->existeFichero()) {
            $this->dispatch('toast-error', ['title' => __('El PDF de esta factura ya no está en el disco: no se puede releer.')]);

            return;
        }

        $texto = LectorPdf::aTexto(Documento::disco()->path($factura->documento->ruta));
        $this->facturaEnCorreccion = $factura->id;

        $this->dispatch('abrir-corregir-campo-plantilla',
            texto: $texto,
            cif: $factura->proveedor->persona->documento_identificativo,
            tipoCampo: (int) $tipoCampo,
            indice: $factura->id,
        );
    }

    /**
     * MarcarPlantillaFactura::guardarPlantilla() manda esto tras corregir el campo: además
     * de guardar la plantilla (arregla las próximas facturas de este proveedor), aquí se
     * actualiza YA la fila concreta que se estaba corrigiendo.
     */
    #[On('plantilla-factura-completada')]
    public function actualizarFacturaCorregida($indice, $valores)
    {
        if ($indice !== $this->facturaEnCorreccion) {
            return;
        }
        $this->facturaEnCorreccion = null;

        $factura = FacturaProveedor::find($indice);
        if (! $factura) {
            return;
        }

        $alta = new AltaProveedorDesdeFactura();

        if (array_key_exists('fecha', $valores)) {
            $factura->fecha_factura = $alta->normalizarFecha($valores['fecha']);
        }
        if (array_key_exists('numero_factura', $valores)) {
            $factura->numero_factura = $valores['numero_factura'];
        }
        if (array_key_exists('importe', $valores)) {
            $factura->importe = $alta->normalizarImporte($valores['importe']);
        }

        $factura->save();

        $this->dispatch('toast-success', ['title' => __('Factura corregida')]);
    }

    /**
     * Cambia a qué actividad pertenece esta factura. Solo antes de contabilizarla: una
     * vez asentada, el proyecto ya viajó al apunte y cambiarlo aquí dejaría la factura
     * diciendo una cosa y el asiento otra.
     */
    public function actualizarActividad($facturaId, $actividadId)
    {
        $factura = FacturaProveedor::whereHas('proveedor.persona', fn ($p) => $p->where('comunidad_id', session('comunidad_actual_id')))
            ->find($facturaId);

        if (! $factura || $factura->asiento_contable) {
            return;
        }

        $actividadId = $actividadId !== '' ? (int) $actividadId : null;

        if ($actividadId !== null && ! Actividad::where('id', $actividadId)->where('comunidad_id', session('comunidad_actual_id'))->exists()) {
            return;
        }

        $factura->update(['actividad_id' => $actividadId]);
    }

    protected function filtroCif(): array
    {
        return [
            'clave'    => 'cif',
            'etiqueta' => __('CIF'),
            'tipo'     => 'texto',
            'aplicar'  => fn ($query, $valor) => $query->whereHas(
                'proveedor.persona',
                fn ($p) => $p->where('documento_identificativo', 'like', "%{$valor}%")
            ),
        ];
    }

    protected function filtroRazonSocial(): array
    {
        return [
            'clave'    => 'razon_social',
            'etiqueta' => __('Razón social'),
            'tipo'     => 'texto',
            'aplicar'  => fn ($query, $valor) => $query->whereHas(
                'proveedor.persona',
                fn ($p) => $p->where('razon_social', 'like', "%{$valor}%")
                    ->orWhere('nombre', 'like', "%{$valor}%")
                    ->orWhere('apellido1', 'like', "%{$valor}%")
                    ->orWhere('apellido2', 'like', "%{$valor}%")
            ),
        ];
    }

    protected function filtroFechaDesde(): array
    {
        return [
            'clave'    => 'fecha_factura_desde',
            'etiqueta' => __('Fecha desde'),
            'tipo'     => 'fecha',
            // fecha_factura se guarda como texto dd/mm/aaaa (ver AltaProveedorDesdeFactura::normalizarFecha),
            // así que hay que reordenarla a aaaa-mm-dd para poder compararla con el rango.
            'aplicar'  => fn ($query, $valor) => $query->whereRaw("STR_TO_DATE(fecha_factura, '%d/%m/%Y') >= ?", [$valor]),
        ];
    }

    protected function filtroFechaHasta(): array
    {
        return [
            'clave'    => 'fecha_factura_hasta',
            'etiqueta' => __('Fecha hasta'),
            'tipo'     => 'fecha',
            'aplicar'  => fn ($query, $valor) => $query->whereRaw("STR_TO_DATE(fecha_factura, '%d/%m/%Y') <= ?", [$valor]),
        ];
    }

    protected function filtroContabilizada(): array
    {
        return [
            'clave'    => 'contabilizada',
            'etiqueta' => __('Contabilizada'),
            'tipo'     => 'select',
            'opciones' => [
                0 => __('Todas'),
                1 => __('Sí'),
                2 => __('No'),
            ],
            'neutro'   => 0,
            'aplicar'  => fn ($query, $valor) => (int) $valor === 1
                ? $query->whereNotNull('asiento_contable')
                : $query->whereNull('asiento_contable'),
        ];
    }

    /** «Pagada»: mismo estado que pinta la columna Pago de la tabla (pendiente() <= 0). */
    protected function filtroPagada(): array
    {
        return [
            'clave'    => 'pagada',
            'etiqueta' => __('Pagada'),
            'tipo'     => 'select',
            'opciones' => [
                0 => __('Todas'),
                1 => __('Sí'),
                2 => __('No'),
            ],
            'neutro'   => 0,
            'aplicar'  => fn ($query, $valor) => (int) $valor === 1
                ? $query->whereColumn('importe_pagado', '>=', 'importe')
                : $query->whereColumn('importe_pagado', '<', 'importe'),
        ];
    }

    public function definicionesFiltro(): array
    {
        return [
            $this->filtroCif(),
            $this->filtroRazonSocial(),
            // Solo tiene sentido si la contabilidad está activa: es la misma condición
            // que decide si se ve la columna y el botón de Contabilizar en la tabla.
            ...(contabilidad_activa() ? [$this->filtroContabilizada()] : []),
            $this->filtroPagada(),
            $this->filtroFechaDesde(),
            $this->filtroFechaHasta(),
        ];
    }

    public function columnasDisponibles(): array
    {
        return [
            'cif'            => __('CIF proveedor'),
            'razon_social'   => __('Razón social'),
            'fecha_factura'  => __('Fecha factura'),
            'numero_factura' => __('Número factura'),
            'importe'        => __('Importe'),
        ];
    }

    /** cif y razon_social viven en proveedor.persona (pedirían join); el resto se ordena tal cual. */
    protected function columnasOrdenables(): ?array
    {
        return ['id', 'fecha_factura', 'importe'];
    }

    /**
     * Manda a la contabilidad lo que le falte a esta factura: el gasto devengado si es la
     * primera vez, y los pagos que se hubieran quedado sin asiento porque la contabilidad
     * falló en su momento. Es explícito a propósito —contabilizar es una decisión de quien
     * lleva la comunidad, no un efecto secundario de teclear la factura— y se puede repetir
     * sin duplicar nada: la referencia de cada asiento lo impide.
     */
    public function contabilizar($facturaId, EnlazarFacturasContabilidad $enlazar)
    {
        $factura = FacturaProveedor::with(['proveedor.persona.comunidad', 'proveedor.tipo'])->find($facturaId);
        if (! $factura) {
            return;
        }

        $soloPagos = $factura->asiento_contable !== null;

        // El motivo se mira antes de preguntar: no tiene sentido confirmar algo que no
        // va a poder hacerse. Con la factura ya asentada, lo que falta son sus pagos.
        if (! $soloPagos && $motivo = $enlazar->motivoNoEnlazable($factura)) {
            $this->dispatch('toast-error', ['title' => $motivo]);

            return;
        }

        if ($soloPagos && ! $factura->faltaPorContabilizar()) {
            return;
        }

        $this->dispatch('swalConfirm', [
            'title'              => $soloPagos ? __('Contabilizar los pagos') : __('Contabilizar la factura'),
            'text'               => $soloPagos
                ? __('Sus pagos se quedaron sin asiento. Se vuelven a mandar a la contabilidad.')
                : __('Se registrará el gasto en la contabilidad. El pago va aparte.'),
            'icon'               => 'question',
            'showCancelButton'   => true,
            'focusConfirm'       => true,
            'confirmButtonColor' => '#16a34a',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, contabilizar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'contabilizar-confirmado',
            'cancelCallback'     => 'contabilizar-cancelado',
            'id'                 => $factura->id,
        ]);
    }

    #[On('contabilizar-cancelado')]
    public function contabilizarCancelado($id = null)
    {
        // Nada que hacer: el evento existe porque swalConfirm siempre emite uno de los dos.
    }

    #[On('contabilizar-confirmado')]
    public function contabilizarConfirmado(
        $id,
        EnlazarFacturasContabilidad $enlazar,
        EnlazarPagosContabilidad $enlazarPagos,
    ) {
        $factura = FacturaProveedor::with(['proveedor.persona.comunidad', 'proveedor.tipo'])->find($id);
        if (! $factura) {
            return;
        }

        try {
            // Sin asiento todavía: primero el gasto. Ya asentada, esto no hace nada y se
            // pasa directamente a sus pagos.
            if ($factura->asiento_contable === null && ! $enlazar->motivoNoEnlazable($factura)) {
                $enlazar->ejecutar([$factura->id]);
                $factura->refresh();
            }

            // Los pagos van después y solo si el gasto ya está: el asiento del pago
            // descarga al acreedor que crea el de la factura.
            $pagosPendientes = $factura->asiento_contable !== null
                ? $factura->pagos()->whereNull('asiento_contable')->pluck('id')->all()
                : [];

            if ($pagosPendientes) {
                $enlazarPagos->ejecutar($pagosPendientes);
            }
        } catch (AsientoInvalidoException|EjercicioCerradoException|EjercicioContableDesconocidoException|CuentaContableDesconocidaException $e) {
            $this->dispatch('toast-error', ['title' => $e->getMessage()]);

            return;
        }

        $this->dispatch('toast-success', ['title' => __('Contabilizado')]);
    }

    /**
     * La consulta base (comunidad y búsqueda), SIN filtros/selección ni orden ni
     * paginación: la usan render(), invertirSeleccion() —que necesita los ids de TODO
     * lo filtrado, no solo de la página— y las acciones en lote.
     */
    private function consultaBase()
    {
        $search = trim($this->search ?? '');

        return FacturaProveedor::with(['proveedor.persona', 'proveedor.tipo', 'documento', 'pagos'])
            // Para saber si a la fila le falta algo por asentar sin preguntar una vez
            // por factura.
            ->withCount(['pagos as pagos_sin_asentar_count' => fn ($q) => $q->whereNull('asiento_contable')])
            ->whereHas('proveedor.persona', fn ($p) => $p->where('comunidad_id', session('comunidad_actual_id')))
            // Ver solo seleccionados manda también sobre la búsqueda: aunque una factura ya
            // no case con el texto buscado, tiene que poder verse para actuar sobre ella.
            ->when($search && ! $this->verSoloSeleccionados, function ($q) use ($search) {
                // Agrupado en un where anidado: si no, el orWhereHas de dentro se
                // desengancha del whereHas de comunidad de arriba y se ve gente de otras.
                $q->where(function ($q2) use ($search) {
                    $q2->where('numero_factura', 'like', "%{$search}%")
                        ->orWhereHas('proveedor.persona', fn ($p) => $p
                            ->where('razon_social', 'like', "%{$search}%")
                            ->orWhere('nombre', 'like', "%{$search}%")
                            ->orWhere('apellido1', 'like', "%{$search}%")
                            ->orWhere('apellido2', 'like', "%{$search}%")
                            ->orWhere('documento_identificativo', 'like', "%{$search}%"));
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
     * Sin nada marcado, la acción en lote cae sobre TODO lo que cumple el filtro actual,
     * no solo lo que se ve en la página: antes de lanzarla a ciegas se avisa de cuántos
     * registros va a tocar y se pide confirmación. Con algo marcado no hace falta: ya es
     * una elección explícita.
     */
    private function confirmarSiSinSeleccion(string $callback, string $participio): bool
    {
        if ($this->seleccionados !== []) {
            return true;
        }

        $total = $this->aplicarFiltros($this->consultaBase())->count();

        if ($total === 0) {
            $this->dispatch('toast-error', ['title' => __('No hay facturas sobre las que actuar')]);

            return false;
        }

        $this->dispatch('swalConfirm', [
            'title'              => __('No hay registros marcados'),
            'text'               => __('Se van a dar por :participio :total registros. ¿Desea continuar?', [
                'participio' => $participio,
                'total'      => $total,
            ]),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'focusConfirm'       => true,
            'confirmButtonColor' => '#16a34a',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, continuar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => $callback,
            'cancelCallback'     => 'accion-lote-cancelada',
        ]);

        return false;
    }

    #[On('accion-lote-cancelada')]
    public function accionLoteCancelada(): void
    {
        // Nada que hacer: el evento existe porque swalConfirm siempre emite uno de los dos.
    }

    /**
     * Contabiliza de golpe lo que le falte a cada factura marcada: el gasto devengado de
     * las que no lo tengan y los pagos que se hubieran quedado sin asiento.
     */
    public function contabilizarLote(EnlazarFacturasContabilidad $enlazar, EnlazarPagosContabilidad $enlazarPagos): void
    {
        if (! $this->confirmarSiSinSeleccion('contabilizar-lote-confirmado', __('contabilizadas'))) {
            return;
        }

        $this->contabilizarLoteConfirmado($enlazar, $enlazarPagos);
    }

    #[On('contabilizar-lote-confirmado')]
    public function contabilizarLoteConfirmado(EnlazarFacturasContabilidad $enlazar, EnlazarPagosContabilidad $enlazarPagos): void
    {
        $ids = $this->idsParaAccion();

        $facturas = $enlazar->ejecutar($ids);

        $pagoIds = PagoFactura::whereIn('factura_proveedor_id', $ids)
            ->whereNull('asiento_contable')
            ->pluck('id')
            ->all();

        $pagos = $pagoIds ? $enlazarPagos->ejecutar($pagoIds) : ['enlazados' => 0, 'omitidos' => 0];

        $this->limpiarSeleccion();

        if ($facturas['enlazadas'] === 0 && $pagos['enlazados'] === 0) {
            $this->dispatch('toast-error', ['title' => __('No se ha contabilizado nada')]);

            return;
        }

        $this->dispatch('toast-success', [
            'title' => __(':facturas facturas y :pagos pagos contabilizados', [
                'facturas' => $facturas['enlazadas'],
                'pagos'    => $pagos['enlazados'],
            ]),
        ]);
    }

    /**
     * Abre el modal con los ids ya congelados: entre abrirlo y confirmar se puede cambiar
     * de página o de filtro, y lo que se paga tiene que ser lo que se vio.
     */
    public function abrirPagoLote(): void
    {
        if (! $this->confirmarSiSinSeleccion('pago-lote-confirmado', __('pagadas'))) {
            return;
        }

        $this->abrirPagoLoteConfirmado();
    }

    #[On('pago-lote-confirmado')]
    public function abrirPagoLoteConfirmado(): void
    {
        $this->pagoLoteIds = $this->idsParaAccion();

        if ($this->pagoLoteIds === []) {
            $this->dispatch('toast-error', ['title' => __('No hay facturas sobre las que actuar')]);

            return;
        }

        $this->pagoLoteFecha       = now()->toDateString();
        $this->pagoLoteUnicoApunte = false;
        $this->resetValidation();
        $this->pagoLoteAbierto     = true;
    }

    public function cerrarPagoLote(): void
    {
        $this->pagoLoteAbierto = false;
        $this->resetValidation();
    }

    /**
     * Paga el pendiente completo de cada factura marcada, en la misma fecha. Las que no
     * se pueden pagar todavía (ya pagadas, sin cuenta bancaria, sin contabilizar cuando
     * hace falta…) se saltan solas, con el mismo motivo que vería quien las pagara una a
     * una desde PagarFactura.
     *
     * Con "único apunte" marcado, todas salen del banco en una sola transferencia: en vez
     * de un asiento por factura, es uno solo con el total (ver
     * EnlazarPagosContabilidad::ejecutarAgrupado). Si el mismo día hay varias
     * transferencias distintas, se pagan en lotes separados, uno por transferencia.
     */
    public function pagarLote(RegistrarPagoFactura $pagos, EnlazarPagosContabilidad $contabilidad): void
    {
        $this->validate([
            'pagoLoteFecha' => ['required', 'date'],
        ], attributes: [
            'pagoLoteFecha' => __('Fecha de pago'),
        ]);

        $facturas = FacturaProveedor::with('proveedor.persona.comunidad')
            ->whereIn('id', $this->pagoLoteIds)
            ->get();

        // Todo el lote sale con la misma fecha: si no cubre la factura más tardía, no se
        // paga ninguna, para no dejar elegir facturas una a una hasta que cuadre.
        $fechaMasTardia = $facturas
            ->map(fn ($factura) => $factura->fecha_factura
                ? Carbon::createFromFormat('d/m/Y', $factura->fecha_factura)->toDateString()
                : null)
            ->filter()
            ->max();

        if ($fechaMasTardia && $this->pagoLoteFecha < $fechaMasTardia) {
            throw ValidationException::withMessages([
                'pagoLoteFecha' => __('Hay una factura del :fecha: la fecha de pago no puede ser anterior.', [
                    'fecha' => Carbon::parse($fechaMasTardia)->format('d/m/Y'),
                ]),
            ]);
        }

        $pagadas       = 0;
        $sinContabilizar = 0;
        $pagoIdsGrupo  = [];

        foreach ($facturas as $factura) {
            if ($pagos->motivoNoPagable($factura, $this->pagoLoteFecha)) {
                continue;
            }

            try {
                // Con "único apunte" el pago se registra sin enlazarlo todavía: el grupo
                // entero se manda a contabilidad de una vez al final, en un solo asiento.
                $pago = $pagos->registrar($factura->id, $this->pagoLoteFecha, enlazarContabilidad: ! $this->pagoLoteUnicoApunte);

                if ($pago) {
                    $pagadas++;

                    if ($this->pagoLoteUnicoApunte) {
                        $pagoIdsGrupo[] = $pago->id;
                    }
                }
            } catch (AsientoInvalidoException|EjercicioCerradoException|EjercicioContableDesconocidoException|CuentaContableDesconocidaException $e) {
                // El pago quedó registrado; lo que falló es su asiento, igual que en
                // PagarFactura. Se sigue con el resto del lote.
                $pagadas++;
                $sinContabilizar++;
            }
        }

        if ($pagoIdsGrupo !== []) {
            try {
                $resultado = $contabilidad->ejecutarAgrupado($pagoIdsGrupo);
                $sinContabilizar += $resultado['omitidos'];
            } catch (AsientoInvalidoException|EjercicioCerradoException|EjercicioContableDesconocidoException|CuentaContableDesconocidaException $e) {
                // Los pagos quedaron registrados; lo que falló es el asiento del grupo.
                $sinContabilizar += count($pagoIdsGrupo);
            }
        }

        $this->pagoLoteAbierto = false;
        $this->pagoLoteIds     = [];
        $this->limpiarSeleccion();

        if ($pagadas === 0) {
            $this->dispatch('toast-error', ['title' => __('No había ninguna factura pagable')]);

            return;
        }

        $this->dispatch('toast-success', [
            'title' => $sinContabilizar
                ? __(':pagadas facturas pagadas; :sinContabilizar sin contabilizar', compact('pagadas', 'sinContabilizar'))
                : __(':count facturas pagadas', ['count' => $pagadas]),
        ]);
    }

    public function render()
    {
        $items = $this->aplicarSeleccion($this->consultaBase())
            // fecha_factura se guarda como texto dd/mm/aaaa (ver filtroFechaDesde): ordenar tal
            // cual sería alfabético, no cronológico, así que se reordena igual que en el filtro.
            ->when($this->sort === 'fecha_factura', fn ($q) => $q->orderByRaw(
                "STR_TO_DATE(fecha_factura, '%d/%m/%Y') {$this->direction}"
            ))
            ->when($this->sort !== 'fecha_factura', fn ($q) => $q->orderBy($this->sort, $this->direction))
            ->paginate($this->lineasXPagina);

        $this->sincronizarSeleccionVisible($items);

        return view('livewire.facturas.lista', [
            'items'       => $items,
            'actividades' => Actividad::where('comunidad_id', session('comunidad_actual_id'))->orderBy('nombre')->pluck('nombre', 'id'),
        ]);
    }
}

<?php

namespace App\Livewire\Sociedades\Proveedores;

use App\Models\PlantillaFactura;
use App\Models\TipoCampoPlantillaFactura;
use App\Services\Facturas\LectorPdf;
use App\Services\Facturas\Plantillas\ExtractorPorCoordenadas;
use App\Services\Facturas\Plantillas\ExtractorPosicional;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Igual que Proveedores\MarcarPlantillaFactura (comunidad), pero sociedad desglosa IVA:
 * tras IMPORTE_BASE se entra en un tramo aparte para marcar 0, 1 o varias cuotas de IVA
 * (no todas las facturas traen todas), y al terminar se marca IMPORTE_TOTAL. Para cada
 * cuota se pide primero el % (a mano, no hay etiqueta de texto fiable que lo distinga:
 * "21%" en la factura ES el valor, no una etiqueta aparte) y luego se marca solo el
 * importe con un único clic, igual que RAZÓN SOCIAL/CIF.
 */
class MarcarPlantillaFactura extends Component
{
    public bool $abrir = false;

    public string $texto = '';
    public ?string $rutaAbsoluta = null;
    public ?int $indiceResultado = null;
    public ?string $cifPlantilla = null;

    public bool $usarPosicion = false;

    protected ?array $bloquesConPosicion = null;

    public ?string $imagenCabecera = null;
    public ?string $hashImagenCabecera = null;
    public bool $imagenCabeceraCargada = false;

    public array $campos = [];
    public int $indiceCampo = 0;
    public array $valores = [];
    public array $valoresDetectados = [];
    public string $valorManual = '';

    /** Tramo actual: 'campos' (secuencia fija) o 'cuotas' (0..N cuotas de IVA antes del total). */
    public string $fase = 'campos';

    /** Cuotas de IVA ya marcadas en esta sesión: [['ancla'=>..,'valor'=>..,'tipo_iva'=>float], ...]. */
    public array $cuotasIva = [];

    /** Sub-paso dentro de una cuota: 'porcentaje' (tecleando el %) o 'valor' (marcando el importe). */
    public string $subEtapaCuota = 'porcentaje';

    /**
     * % ya confirmado de la cuota en curso, a falta de marcar su importe. Pública a
     * propósito: Livewire solo conserva entre peticiones las propiedades públicas, y esto
     * hay que leerlo en la petición del clic de marcado, posterior a la que fijó el %.
     */
    public ?float $tipoIvaPendiente = null;

    public string $tipoIvaManual = '';

    /** Etiqueta ya marcada de la cuota en curso, a falta de marcar su importe. Pública por el mismo motivo. */
    public ?array $etiquetaPendienteCuota = null;

    /** Campo (de $valores) que se está volviendo a marcar sin perder el resto de lo ya hecho. */
    public ?int $campoEnCorreccion = null;

    /** Pública por el mismo motivo que $tipoIvaPendiente: se lee en una petición posterior. */
    public ?array $retornoTrasCorreccion = null;

    protected array $etiquetas = [
        TipoCampoPlantillaFactura::RAZON_SOCIAL   => 'la RAZÓN SOCIAL del proveedor',
        TipoCampoPlantillaFactura::CIF            => 'el CIF del proveedor',
        TipoCampoPlantillaFactura::FECHA          => 'la FECHA',
        TipoCampoPlantillaFactura::NUMERO_FACTURA => 'el NÚMERO DE FACTURA',
        TipoCampoPlantillaFactura::IMPORTE_BASE   => 'la BASE IMPONIBLE',
        TipoCampoPlantillaFactura::IMPORTE_TOTAL  => 'el IMPORTE TOTAL',
    ];

    protected array $clavesPayload = [
        TipoCampoPlantillaFactura::RAZON_SOCIAL   => 'razon_social',
        TipoCampoPlantillaFactura::CIF            => 'cif',
        TipoCampoPlantillaFactura::FECHA          => 'fecha',
        TipoCampoPlantillaFactura::NUMERO_FACTURA => 'numero_factura',
        TipoCampoPlantillaFactura::IMPORTE_BASE   => 'importe_base',
        TipoCampoPlantillaFactura::IMPORTE_TOTAL  => 'importe_total',
    ];

    protected array $camposConAncla = [
        TipoCampoPlantillaFactura::CIF,
        TipoCampoPlantillaFactura::FECHA,
        TipoCampoPlantillaFactura::NUMERO_FACTURA,
        TipoCampoPlantillaFactura::IMPORTE_BASE,
        TipoCampoPlantillaFactura::IMPORTE_TOTAL,
    ];

    /** Campos que cambian de una factura a otra: etiqueta y valor se marcan por separado. */
    protected array $camposConEtiquetaValor = [
        TipoCampoPlantillaFactura::FECHA,
        TipoCampoPlantillaFactura::NUMERO_FACTURA,
        TipoCampoPlantillaFactura::IMPORTE_BASE,
        TipoCampoPlantillaFactura::IMPORTE_TOTAL,
    ];

    public ?string $subEtapa = null;
    public ?array $etiquetaPendiente = null;

    #[On('abrir-marcar-plantilla-factura-sociedad')]
    public function mostrar($texto, $cif, $razonSocial, $indice, $fecha = null, $rutaAbsoluta = null)
    {
        $this->texto              = $texto;
        $this->rutaAbsoluta       = $rutaAbsoluta;
        $this->bloquesConPosicion = null;
        $this->imagenCabecera        = null;
        $this->imagenCabeceraCargada = false;
        $this->hashImagenCabecera    = null;
        $this->usarPosicion       = false;
        $this->indiceResultado   = $indice;
        $this->cifPlantilla      = null;
        $this->valores           = [];
        $this->indiceCampo       = 0;
        $this->valorManual       = '';
        $this->fase               = 'campos';
        $this->cuotasIva          = [];
        $this->subEtapaCuota      = 'porcentaje';
        $this->tipoIvaPendiente   = null;
        $this->tipoIvaManual      = '';
        $this->etiquetaPendienteCuota = null;
        $this->campoEnCorreccion  = null;
        $this->retornoTrasCorreccion = null;
        $this->valoresDetectados = [
            TipoCampoPlantillaFactura::RAZON_SOCIAL => $razonSocial,
            TipoCampoPlantillaFactura::CIF          => $cif,
            TipoCampoPlantillaFactura::FECHA        => $fecha,
        ];

        $this->campos = [
            TipoCampoPlantillaFactura::RAZON_SOCIAL,
            TipoCampoPlantillaFactura::CIF,
            TipoCampoPlantillaFactura::FECHA,
            TipoCampoPlantillaFactura::NUMERO_FACTURA,
            TipoCampoPlantillaFactura::IMPORTE_BASE,
        ];

        $this->cargarImagenCabecera();
        $this->iniciarSubEtapaCampoActual();
        $this->abrir = true;
    }

    /** Proveedor con plantilla ya existente, pero un campo (que no sea una cuota de IVA) salió mal. */
    #[On('abrir-corregir-campo-plantilla-sociedad')]
    public function corregirCampo($texto, $cif, $tipoCampo, $indice, $rutaAbsoluta = null)
    {
        $this->texto              = $texto;
        $this->rutaAbsoluta       = $rutaAbsoluta;
        $this->bloquesConPosicion = null;
        $this->imagenCabecera        = null;
        $this->imagenCabeceraCargada = false;
        $this->hashImagenCabecera    = null;
        $this->usarPosicion       = false;
        $this->indiceResultado   = $indice;
        $this->cifPlantilla      = $cif;
        $this->valores           = [];
        $this->indiceCampo       = 0;
        $this->valorManual       = '';
        $this->fase               = 'campos';
        $this->cuotasIva          = [];
        $this->campoEnCorreccion  = null;
        $this->retornoTrasCorreccion = null;
        $this->valoresDetectados = [];
        $this->campos            = [(int) $tipoCampo];

        $this->cargarImagenCabecera();
        $this->iniciarSubEtapaCampoActual();
        $this->abrir = true;
    }

    /** Vuelve a pedir un campo ya marcado (de la lista "Marcado hasta ahora"), sin perder el resto de lo ya hecho. */
    public function remarcar($tipoCampoId)
    {
        $tipoCampoId = (int) $tipoCampoId;

        if ($this->campoEnCorreccion !== null || ! isset($this->valores[$tipoCampoId])) {
            return;
        }

        $this->retornoTrasCorreccion = [
            'fase'        => $this->fase,
            'campos'      => $this->campos,
            'indiceCampo' => $this->indiceCampo,
        ];
        $this->campoEnCorreccion = $tipoCampoId;

        $this->fase        = 'campos';
        $this->campos      = [$tipoCampoId];
        $this->indiceCampo = 0;
        $this->usarPosicion = false;
        $this->iniciarSubEtapaCampoActual();
    }

    public function usarValorDetectado()
    {
        $tipo  = $this->campos[$this->indiceCampo] ?? null;
        $valor = $tipo !== null ? ($this->valoresDetectados[$tipo] ?? null) : null;

        if ($valor === null) {
            return;
        }

        $this->valores[$tipo] = ['ancla' => null, 'valor' => $valor];
        $this->avanzar();
    }

    public function marcarComoNoEncontrado()
    {
        $this->avanzar();
    }

    public function marcarManual()
    {
        $tipo  = $this->campos[$this->indiceCampo] ?? null;
        $valor = trim($this->valorManual);

        if ($tipo === null || $valor === '') {
            return;
        }

        $this->valores[$tipo] = ['ancla' => null, 'valor' => $valor];
        $this->valorManual = '';
        $this->avanzar();
    }

    public function activarPosicion()
    {
        $this->usarPosicion = true;
        $this->etiquetaPendiente = null;
        $this->subEtapa = null;
    }

    public function marcar($inicio, $fin)
    {
        if ($fin <= $inicio) {
            return;
        }

        if ($this->fase === 'cuotas') {
            $this->marcarCuota((int) $inicio, (int) $fin);

            return;
        }

        $tipo = $this->campos[$this->indiceCampo] ?? null;
        if ($tipo === null) {
            return;
        }

        if ($this->usarPosicion) {
            $this->marcarPorPosicion($tipo, (int) $inicio, (int) $fin);

            return;
        }

        if (! in_array($tipo, $this->camposConEtiquetaValor, true)) {
            $resultado = (new ExtractorPosicional())->construirAncla($this->texto, (int) $inicio, (int) $fin);
            if ($resultado['valor'] === '') {
                return;
            }

            $this->valores[$tipo] = $resultado;
            $this->avanzar();

            return;
        }

        if ($this->subEtapa === 'etiqueta') {
            $texto = trim(mb_substr($this->texto, (int) $inicio, (int) $fin - (int) $inicio));
            if ($texto === '') {
                return;
            }

            $this->etiquetaPendiente = ['inicio' => (int) $inicio, 'fin' => (int) $fin];
            $this->subEtapa = 'valor';

            return;
        }

        if (! $this->etiquetaPendiente) {
            return;
        }

        $extractor = new ExtractorPosicional();
        $resultado = $extractor->construirAnclaEtiquetaValor(
            $this->texto,
            $this->etiquetaPendiente['inicio'], $this->etiquetaPendiente['fin'],
            (int) $inicio, (int) $fin
        );

        if ($resultado['valor'] === '') {
            return;
        }

        $revalidado = $extractor->buscarPorEtiquetaYDelta(
            $this->texto, $resultado['ancla'], $resultado['delta_columna'], $resultado['delta_lineas'], $resultado['longitud_valor']
        );

        if ($revalidado !== $resultado['valor']) {
            $this->dispatch('toast-error', ['title' => __('Esa etiqueta no es lo bastante única en el texto: prueba a marcar otra.')]);
            $this->etiquetaPendiente = null;
            $this->subEtapa = 'etiqueta';

            return;
        }

        $this->valores[$tipo] = $resultado;
        $this->etiquetaPendiente = null;
        $this->avanzar();
    }

    /**
     * El % ya se ha tecleado y confirmado. Después, igual que FECHA/NUMERO_FACTURA/...:
     * primero se marca la ETIQUETA de esa cuota (ej. "Cuota IVA 21%") y luego su IMPORTE,
     * anclados el uno al otro para poder reencontrarlos en la próxima factura.
     */
    protected function marcarCuota(int $inicio, int $fin)
    {
        if ($this->subEtapaCuota === 'etiqueta') {
            $texto = trim(mb_substr($this->texto, $inicio, $fin - $inicio));
            if ($texto === '') {
                return;
            }

            $this->etiquetaPendienteCuota = ['inicio' => $inicio, 'fin' => $fin];
            $this->subEtapaCuota = 'valor';

            return;
        }

        if ($this->subEtapaCuota !== 'valor' || $this->tipoIvaPendiente === null || ! $this->etiquetaPendienteCuota) {
            return;
        }

        $extractor = new ExtractorPosicional();
        $resultado = $extractor->construirAnclaEtiquetaValor(
            $this->texto,
            $this->etiquetaPendienteCuota['inicio'], $this->etiquetaPendienteCuota['fin'],
            $inicio, $fin
        );

        if ($resultado['valor'] === '') {
            return;
        }

        $revalidado = $extractor->buscarPorEtiquetaYDelta(
            $this->texto, $resultado['ancla'], $resultado['delta_columna'], $resultado['delta_lineas'], $resultado['longitud_valor']
        );

        if ($revalidado !== $resultado['valor']) {
            $this->dispatch('toast-error', ['title' => __('Esa etiqueta no es lo bastante única en el texto: prueba a marcar otra.')]);
            $this->etiquetaPendienteCuota = null;
            $this->subEtapaCuota = 'etiqueta';

            return;
        }

        $this->cuotasIva[] = $resultado + ['tipo_iva' => $this->tipoIvaPendiente];

        $this->tipoIvaPendiente = null;
        $this->tipoIvaManual = '';
        $this->etiquetaPendienteCuota = null;
        $this->subEtapaCuota = 'porcentaje';
    }

    /** Primer paso de cada cuota: fija el % a mano antes de marcar etiqueta+importe. */
    public function confirmarPorcentajeCuota()
    {
        $tipoIva = trim(str_replace(',', '.', $this->tipoIvaManual));
        if ($tipoIva === '' || ! is_numeric($tipoIva)) {
            $this->dispatch('toast-error', ['title' => __('Indica un % de IVA válido (ej. 21, 10, 4, 0).')]);

            return;
        }

        $this->tipoIvaPendiente = (float) $tipoIva;
        $this->subEtapaCuota = 'etiqueta';
    }

    /** Vuelve a pedir el % de la cuota en curso (por si se tecleó mal), sin haber marcado aún nada de ella. */
    public function cambiarPorcentajeCuota()
    {
        $this->tipoIvaPendiente = null;
        $this->etiquetaPendienteCuota = null;
        $this->subEtapaCuota = 'porcentaje';
    }

    public function quitarCuota($indice)
    {
        unset($this->cuotasIva[$indice]);
        $this->cuotasIva = array_values($this->cuotasIva);
    }

    /** Esta factura no traía IVA que marcar (exenta), o ya se han marcado todas las que traía. */
    public function terminarCuotas()
    {
        $this->fase = 'total';
        $this->subEtapaCuota = 'porcentaje';
        $this->tipoIvaPendiente = null;
        $this->tipoIvaManual = '';
        $this->etiquetaPendienteCuota = null;

        $this->campos = [TipoCampoPlantillaFactura::IMPORTE_TOTAL];
        $this->indiceCampo = 0;
        $this->iniciarSubEtapaCampoActual();
    }

    protected function marcarPorPosicion(int $tipo, int $inicio, int $fin)
    {
        if (! $this->rutaAbsoluta) {
            $this->dispatch('toast-error', ['title' => __('No se puede anclar por posición: no se encontró el PDF original.')]);

            return;
        }

        $ancla = (new ExtractorPorCoordenadas())->construirAncla($this->texto, $inicio, $fin, $this->bloques());

        if ($ancla === null) {
            $this->dispatch('toast-error', ['title' => __('No se pudo anclar por posición esa selección: prueba a seleccionar solo el valor exacto.')]);

            return;
        }

        $this->valores[$tipo] = $ancla + ['ancla' => null];
        $this->usarPosicion = false;
        $this->avanzar();
    }

    protected function bloques(): array
    {
        if ($this->bloquesConPosicion === null) {
            $this->bloquesConPosicion = LectorPdf::aBloquesConPosicion($this->rutaAbsoluta);
        }

        return $this->bloquesConPosicion;
    }

    protected function cargarImagenCabecera(): void
    {
        if ($this->imagenCabeceraCargada || ! $this->rutaAbsoluta) {
            return;
        }

        $this->imagenCabeceraCargada = true;
        $bytes = LectorPdf::extraerImagenPrincipal($this->rutaAbsoluta);
        $this->imagenCabecera     = $bytes ? 'data:image/png;base64,' . base64_encode($bytes) : null;
        $this->hashImagenCabecera = $bytes ? hash('sha256', $bytes) : null;
    }

    protected function avanzar()
    {
        $this->indiceCampo++;
        $this->etiquetaPendiente = null;

        if ($this->indiceCampo >= count($this->campos)) {
            // Estábamos corrigiendo un único campo desde la lista "Marcado hasta ahora":
            // se ha actualizado su valor, se vuelve exactamente a donde estaba el flujo.
            if ($this->campoEnCorreccion !== null) {
                $this->fase        = $this->retornoTrasCorreccion['fase'];
                $this->campos      = $this->retornoTrasCorreccion['campos'];
                $this->indiceCampo = $this->retornoTrasCorreccion['indiceCampo'];
                $this->campoEnCorreccion = null;
                $this->retornoTrasCorreccion = null;

                if ($this->fase !== 'cuotas') {
                    $this->iniciarSubEtapaCampoActual();
                }

                return;
            }

            if ($this->fase === 'campos') {
                $this->fase = 'cuotas';
                $this->subEtapaCuota = 'porcentaje';

                return;
            }

            // fase === 'total'
            $this->guardarPlantilla();

            return;
        }

        $this->iniciarSubEtapaCampoActual();
    }

    protected function iniciarSubEtapaCampoActual(): void
    {
        $tipo = $this->campos[$this->indiceCampo] ?? null;
        $this->subEtapa = ($tipo !== null && in_array($tipo, $this->camposConEtiquetaValor, true)) ? 'etiqueta' : null;
        $this->etiquetaPendiente = null;
        $this->usarPosicion = false;
    }

    protected function guardarPlantilla()
    {
        $cif = $this->cifPlantilla ?: ($this->valores[TipoCampoPlantillaFactura::CIF]['valor'] ?? null);
        if (! $cif) {
            $this->dispatch('toast-error', ['title' => __('Sin CIF no se puede guardar la plantilla')]);
            $this->cerrar();

            return;
        }

        $campos = [];
        foreach ($this->camposConAncla as $tipoCampoId) {
            if (isset($this->valores[$tipoCampoId])) {
                $campos[$tipoCampoId] = $this->valores[$tipoCampoId];
            }
        }

        if ($this->cuotasIva) {
            $campos[TipoCampoPlantillaFactura::CUOTA_IVA] = $this->cuotasIva;
        }

        PlantillaFactura::guardarDesdeCampos(
            $cif,
            $this->valores[TipoCampoPlantillaFactura::RAZON_SOCIAL]['valor'] ?? null,
            $campos,
            $this->hashImagenCabecera
        );

        $payload = ['cif' => $cif];
        foreach ($this->valores as $tipoCampoId => $datos) {
            if (isset($this->clavesPayload[$tipoCampoId])) {
                $payload[$this->clavesPayload[$tipoCampoId]] = $datos['valor'];
            }
        }
        if ($this->cuotasIva) {
            $payload['cuotas_iva'] = array_map(fn ($c) => ['tipo_iva' => $c['tipo_iva'], 'importe' => $c['valor']], $this->cuotasIva);
        }

        $this->dispatch('plantilla-factura-sociedad-completada', indice: $this->indiceResultado, valores: $payload);

        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        $campoActual = $this->fase === 'cuotas' ? null : ($this->campos[$this->indiceCampo] ?? null);

        return view('livewire.sociedades.proveedores.marcar-plantilla-factura', [
            'etiquetas'            => array_map(fn ($e) => __($e), $this->etiquetas),
            'etiquetaCampoActual'  => $campoActual ? __($this->etiquetas[$campoActual]) : null,
            'valorDetectadoActual' => $campoActual ? ($this->valoresDetectados[$campoActual] ?? null) : null,
            'esRazonSocial'        => $campoActual === TipoCampoPlantillaFactura::RAZON_SOCIAL,
            'permiteValorManual'   => in_array($campoActual, [TipoCampoPlantillaFactura::RAZON_SOCIAL, TipoCampoPlantillaFactura::CIF], true),
            'puedeAnclarPorPosicion' => $campoActual !== null && $campoActual !== TipoCampoPlantillaFactura::RAZON_SOCIAL && ! $this->usarPosicion,
            'pidiendoEtiqueta'     => $campoActual !== null && in_array($campoActual, $this->camposConEtiquetaValor, true) && $this->subEtapa === 'etiqueta',
            'pidiendoValorConEtiqueta' => $this->fase !== 'cuotas' && $this->subEtapa === 'valor',
            'textoEtiquetaMarcada' => $this->etiquetaPendiente
                ? trim(mb_substr($this->texto, $this->etiquetaPendiente['inicio'], $this->etiquetaPendiente['fin'] - $this->etiquetaPendiente['inicio']))
                : null,
            'enCuotas'             => $this->fase === 'cuotas',
            'textoEtiquetaCuota'   => $this->etiquetaPendienteCuota
                ? trim(mb_substr($this->texto, $this->etiquetaPendienteCuota['inicio'], $this->etiquetaPendienteCuota['fin'] - $this->etiquetaPendienteCuota['inicio']))
                : null,
        ]);
    }
}

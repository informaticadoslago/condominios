<?php

namespace App\Livewire\Proveedores;

use App\Models\PlantillaFactura;
use App\Models\TipoCampoPlantillaFactura;
use App\Services\Facturas\LectorPdf;
use App\Services\Facturas\Plantillas\ExtractorPorCoordenadas;
use App\Services\Facturas\Plantillas\ExtractorPosicional;
use Livewire\Attributes\On;
use Livewire\Component;

class MarcarPlantillaFactura extends Component
{
    public bool $abrir = false;

    public string $texto = '';
    public ?string $rutaAbsoluta = null;
    public ?int $indiceResultado = null;
    public ?string $cifPlantilla = null;

    /** Activado con el botón "no hay etiqueta": el siguiente marcado ancla por posición en la página, no por texto. */
    public bool $usarPosicion = false;

    /** Bloques con posición del PDF actual (pdftotext -bbox-layout), cargados solo si hace falta. */
    protected ?array $bloquesConPosicion = null;

    /**
     * Imagen de cabecera del PDF (data URI) y su huella, por si la razón social/CIF solo están
     * "quemados" en una imagen (ver LectorPdf::extraerImagenPrincipal). Públicas a propósito:
     * Livewire solo conserva entre peticiones las propiedades públicas, y guardarPlantilla()
     * necesita $hashImagenCabecera en una petición posterior a la que la calculó.
     */
    public ?string $imagenCabecera = null;
    public ?string $hashImagenCabecera = null;
    public bool $imagenCabeceraCargada = false;

    public array $campos = [];
    public int $indiceCampo = 0;
    public array $valores = [];
    public array $valoresDetectados = [];
    public string $valorManual = '';

    protected array $etiquetas = [
        TipoCampoPlantillaFactura::RAZON_SOCIAL   => 'la RAZÓN SOCIAL del proveedor',
        TipoCampoPlantillaFactura::CIF             => 'el CIF del proveedor',
        TipoCampoPlantillaFactura::FECHA          => 'la FECHA',
        TipoCampoPlantillaFactura::NUMERO_FACTURA  => 'el NÚMERO DE FACTURA',
        TipoCampoPlantillaFactura::IMPORTE         => 'el IMPORTE TOTAL',
    ];

    protected array $clavesPayload = [
        TipoCampoPlantillaFactura::RAZON_SOCIAL   => 'razon_social',
        TipoCampoPlantillaFactura::CIF             => 'cif',
        TipoCampoPlantillaFactura::FECHA          => 'fecha',
        TipoCampoPlantillaFactura::NUMERO_FACTURA  => 'numero_factura',
        TipoCampoPlantillaFactura::IMPORTE         => 'importe',
    ];

    /** Campos cuya posición se usa para reextraer en facturas siguientes (razón social no cambia, no hace falta). */
    protected array $camposConAncla = [
        TipoCampoPlantillaFactura::CIF,
        TipoCampoPlantillaFactura::FECHA,
        TipoCampoPlantillaFactura::NUMERO_FACTURA,
        TipoCampoPlantillaFactura::IMPORTE,
    ];

    /**
     * Campos que cambian de una factura a otra: para estos, el ancla no se adivina por
     * proximidad (falla si esa etiqueta se repite en el documento) — el usuario marca la
     * ETIQUETA y el VALOR por separado y se guarda su posición relativa. CIF no lo necesita
     * (es constante por proveedor, se guarda una vez y ya no se re-busca de verdad).
     */
    protected array $camposConEtiquetaValor = [
        TipoCampoPlantillaFactura::FECHA,
        TipoCampoPlantillaFactura::NUMERO_FACTURA,
        TipoCampoPlantillaFactura::IMPORTE,
    ];

    /** null si el campo actual no es de $camposConEtiquetaValor; si no, 'etiqueta' o 'valor'. */
    public ?string $subEtapa = null;

    /** Guarda la etiqueta ya marcada mientras se espera a que se marque el valor. */
    public ?array $etiquetaPendiente = null;

    /** Proveedor nuevo (sin plantilla todavía): se marcan los 5 campos en secuencia. */
    #[On('abrir-marcar-plantilla-factura')]
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
        $this->valoresDetectados = [
            TipoCampoPlantillaFactura::RAZON_SOCIAL => $razonSocial,
            TipoCampoPlantillaFactura::CIF           => $cif,
            TipoCampoPlantillaFactura::FECHA         => $fecha,
        ];

        $this->campos = [
            TipoCampoPlantillaFactura::RAZON_SOCIAL,
            TipoCampoPlantillaFactura::CIF,
            TipoCampoPlantillaFactura::FECHA,
            TipoCampoPlantillaFactura::NUMERO_FACTURA,
            TipoCampoPlantillaFactura::IMPORTE,
        ];

        $this->cargarImagenCabecera();
        $this->iniciarSubEtapaCampoActual();
        $this->abrir = true;
    }

    /** Proveedor con plantilla ya existente, pero un campo concreto salió mal: se corrige solo ese. */
    #[On('abrir-corregir-campo-plantilla')]
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
        $this->valoresDetectados = [];
        $this->campos            = [(int) $tipoCampo];

        $this->cargarImagenCabecera();
        $this->iniciarSubEtapaCampoActual();
        $this->abrir = true;
    }

    /** El valor que ya había detectado la regex genérica era correcto: lo damos por bueno sin marcar. */
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

    /** El campo realmente no está en esta factura (ej. no hay nº de factura separado): se salta sin marcar nada. */
    public function marcarComoNoEncontrado()
    {
        $this->avanzar();
    }

    /** La razón social a veces solo existe como logo/imagen: no hay texto que seleccionar. */
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

    /** No hay ninguna etiqueta de texto cerca del valor (está quemada en una imagen): ancla por posición en la página. */
    public function activarPosicion()
    {
        $this->usarPosicion = true;
        $this->etiquetaPendiente = null;
        $this->subEtapa = null;
    }

    public function marcar($inicio, $fin)
    {
        $tipo = $this->campos[$this->indiceCampo] ?? null;
        if ($tipo === null || $fin <= $inicio) {
            return;
        }

        if ($this->usarPosicion) {
            $this->marcarPorPosicion($tipo, (int) $inicio, (int) $fin);

            return;
        }

        if (! in_array($tipo, $this->camposConEtiquetaValor, true)) {
            // Campo de siempre: un único marcado es el valor, el ancla se adivina por proximidad.
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

        // subEtapa === 'valor'
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

        // Autovalidación: si la etiqueta no es lo bastante única (se reencuentra a sí misma en
        // otro sitio distinto), es mejor pedir otra que guardar algo que fallará en la próxima factura.
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

    /** Bloques con posición del PDF actual, cargados una sola vez por apertura del modal. */
    protected function bloques(): array
    {
        if ($this->bloquesConPosicion === null) {
            $this->bloquesConPosicion = LectorPdf::aBloquesConPosicion($this->rutaAbsoluta);
        }

        return $this->bloquesConPosicion;
    }

    /** Imagen de cabecera del PDF (si tiene alguna embebida), para leer a ojo razón social/CIF que solo están ahí. */
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

        PlantillaFactura::guardarDesdeCampos(
            $cif,
            $this->valores[TipoCampoPlantillaFactura::RAZON_SOCIAL]['valor'] ?? null,
            $campos,
            $this->hashImagenCabecera
        );

        // Solo mandamos de vuelta lo que se ha marcado en esta sesión (para no pisar, al
        // corregir un único campo, los otros valores ya buenos que hay en ResultadoFactura).
        $payload = ['cif' => $cif];
        foreach ($this->valores as $tipoCampoId => $datos) {
            $payload[$this->clavesPayload[$tipoCampoId]] = $datos['valor'];
        }

        $this->dispatch('plantilla-factura-completada', indice: $this->indiceResultado, valores: $payload);

        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        $campoActual = $this->campos[$this->indiceCampo] ?? null;

        return view('livewire.proveedores.marcar-plantilla-factura', [
            'etiquetaCampoActual'  => $campoActual ? __($this->etiquetas[$campoActual]) : null,
            'valorDetectadoActual' => $campoActual ? ($this->valoresDetectados[$campoActual] ?? null) : null,
            'etiquetas'            => array_map(fn ($e) => __($e), $this->etiquetas),
            'esRazonSocial'        => $campoActual === TipoCampoPlantillaFactura::RAZON_SOCIAL,
            'permiteValorManual'   => in_array($campoActual, [TipoCampoPlantillaFactura::RAZON_SOCIAL, TipoCampoPlantillaFactura::CIF], true),
            'puedeAnclarPorPosicion' => $campoActual !== null && $campoActual !== TipoCampoPlantillaFactura::RAZON_SOCIAL && ! $this->usarPosicion,
            'pidiendoEtiqueta'     => $campoActual !== null && in_array($campoActual, $this->camposConEtiquetaValor, true) && $this->subEtapa === 'etiqueta',
            'pidiendoValorConEtiqueta' => $this->subEtapa === 'valor',
            'textoEtiquetaMarcada' => $this->etiquetaPendiente
                ? trim(mb_substr($this->texto, $this->etiquetaPendiente['inicio'], $this->etiquetaPendiente['fin'] - $this->etiquetaPendiente['inicio']))
                : null,
        ]);
    }
}

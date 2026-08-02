<?php

namespace App\Livewire\Proveedores;

use App\Models\PlantillaFactura;
use App\Models\TipoCampoPlantillaFactura;
use App\Services\Facturas\Plantillas\ExtractorPosicional;
use Livewire\Attributes\On;
use Livewire\Component;

class MarcarPlantillaFactura extends Component
{
    public bool $abrir = false;

    public string $texto = '';
    public ?int $indiceResultado = null;
    public ?string $cifPlantilla = null;

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
    public function mostrar($texto, $cif, $razonSocial, $indice, $fecha = null)
    {
        $this->texto             = $texto;
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

        $this->iniciarSubEtapaCampoActual();
        $this->abrir = true;
    }

    /** Proveedor con plantilla ya existente, pero un campo concreto salió mal: se corrige solo ese. */
    #[On('abrir-corregir-campo-plantilla')]
    public function corregirCampo($texto, $cif, $tipoCampo, $indice)
    {
        $this->texto             = $texto;
        $this->indiceResultado   = $indice;
        $this->cifPlantilla      = $cif;
        $this->valores           = [];
        $this->indiceCampo       = 0;
        $this->valorManual       = '';
        $this->valoresDetectados = [];
        $this->campos            = [(int) $tipoCampo];

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

    public function marcar($inicio, $fin)
    {
        $tipo = $this->campos[$this->indiceCampo] ?? null;
        if ($tipo === null || $fin <= $inicio) {
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
            $campos
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
            'pidiendoEtiqueta'     => $campoActual !== null && in_array($campoActual, $this->camposConEtiquetaValor, true) && $this->subEtapa === 'etiqueta',
            'pidiendoValorConEtiqueta' => $this->subEtapa === 'valor',
            'textoEtiquetaMarcada' => $this->etiquetaPendiente
                ? trim(mb_substr($this->texto, $this->etiquetaPendiente['inicio'], $this->etiquetaPendiente['fin'] - $this->etiquetaPendiente['inicio']))
                : null,
        ]);
    }
}

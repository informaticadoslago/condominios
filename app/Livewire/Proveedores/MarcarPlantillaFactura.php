<?php

namespace App\Livewire\Proveedores;

use App\Models\CampoPlantillaFactura;
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

    /** Proveedor nuevo (sin plantilla todavía): se marcan los 5 campos en secuencia. */
    #[On('abrir-marcar-plantilla-factura')]
    public function mostrar($texto, $cif, $razonSocial, $indice)
    {
        $this->texto             = $texto;
        $this->indiceResultado   = $indice;
        $this->cifPlantilla      = null;
        $this->valores           = [];
        $this->indiceCampo       = 0;
        $this->valoresDetectados = [
            TipoCampoPlantillaFactura::RAZON_SOCIAL => $razonSocial,
            TipoCampoPlantillaFactura::CIF           => $cif,
        ];

        $this->campos = [
            TipoCampoPlantillaFactura::RAZON_SOCIAL,
            TipoCampoPlantillaFactura::CIF,
            TipoCampoPlantillaFactura::FECHA,
            TipoCampoPlantillaFactura::NUMERO_FACTURA,
            TipoCampoPlantillaFactura::IMPORTE,
        ];

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
        $this->valoresDetectados = [];
        $this->campos            = [(int) $tipoCampo];

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

    public function marcar($inicio, $fin)
    {
        $tipo = $this->campos[$this->indiceCampo] ?? null;
        if ($tipo === null || $fin <= $inicio) {
            return;
        }

        $resultado = (new ExtractorPosicional())->construirAncla($this->texto, (int) $inicio, (int) $fin);
        if ($resultado['valor'] === '') {
            return;
        }

        $this->valores[$tipo] = $resultado;
        $this->avanzar();
    }

    protected function avanzar()
    {
        $this->indiceCampo++;

        if ($this->indiceCampo >= count($this->campos)) {
            $this->guardarPlantilla();
        }
    }

    protected function guardarPlantilla()
    {
        $cif = $this->cifPlantilla ?: ($this->valores[TipoCampoPlantillaFactura::CIF]['valor'] ?? null);
        if (! $cif) {
            $this->dispatch('toast-error', ['title' => __('Sin CIF no se puede guardar la plantilla')]);
            $this->cerrar();

            return;
        }

        $atributos = [];
        if (isset($this->valores[TipoCampoPlantillaFactura::RAZON_SOCIAL])) {
            $atributos['razon_social'] = $this->valores[TipoCampoPlantillaFactura::RAZON_SOCIAL]['valor'];
        }

        $plantilla = PlantillaFactura::firstOrNew(['cif' => $cif]);
        $plantilla->fill($atributos);
        $plantilla->save();

        foreach ($this->camposConAncla as $tipoCampoId) {
            $datos = $this->valores[$tipoCampoId] ?? null;
            if (! $datos || ! $datos['ancla']) {
                continue; // no se ha marcado en esta sesión, o se aceptó tal cual sin posición
            }

            CampoPlantillaFactura::updateOrCreate(
                ['plantilla_factura_id' => $plantilla->id, 'tipo_campo_plantilla_factura_id' => $tipoCampoId],
                ['texto_ancla' => $datos['ancla'], 'valor_ejemplo' => $datos['valor']]
            );
        }

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
        ]);
    }
}

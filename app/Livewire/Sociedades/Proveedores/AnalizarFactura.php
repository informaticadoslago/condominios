<?php

namespace App\Livewire\Sociedades\Proveedores;

use App\Models\Documento;
use App\Models\PlantillaFactura;
use App\Models\Sociedad;
use App\Models\TipoCampoPlantillaFactura;
use App\Services\Facturas\ExtractorDatosFactura;
use App\Services\Facturas\LectorPdf;
use App\Services\Facturas\Plantillas\ExtractorPorCoordenadas;
use App\Services\Facturas\Plantillas\ExtractorPosicional;
use App\Services\Facturas\VerifactuQrLector;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Igual que Proveedores\AnalizarFactura (comunidad), pero resolviendo base/cuotas de IVA/total en vez de un único importe. */
class AnalizarFactura extends Component
{
    use WithFileUploads;

    public bool $abrir = false;

    public array $facturas = [];

    protected function rules()
    {
        return [
            'facturas.*' => 'file|mimes:pdf|max:10240',
        ];
    }

    #[On('abrir-analizar-factura-sociedad')]
    public function mostrar()
    {
        $this->reset('facturas');
        $this->resetErrorBag();
        $this->abrir = true;
    }

    public function updatedFacturas()
    {
        $this->validate();
    }

    public function quitar($index)
    {
        unset($this->facturas[$index]);
        $this->facturas = array_values($this->facturas);
    }

    public function procesar()
    {
        $this->validate();

        if (empty($this->facturas)) {
            return;
        }

        $resultados = [];
        $cifSociedad = Sociedad::find(session('sociedad_actual_id'))?->persona?->documento_identificativo;

        foreach ($this->facturas as $factura) {
            $metadatos = Documento::subirFichero($factura, enBorrador: true);

            $ruta         = ltrim(trim($metadatos['camino'], '/') . '/' . $metadatos['nombrefichero'], '/');
            $rutaAbsoluta = Documento::disco()->path($ruta);
            $texto        = LectorPdf::aTexto($rutaAbsoluta);
            $datos        = (new ExtractorDatosFactura())->extraer($texto, $cifSociedad);

            $base = array_merge($metadatos, [
                'ruta'          => $ruta,
                'texto'         => $texto,
                'datos'         => $datos,
                'con_plantilla' => false,
            ]);

            $verifactu = (new VerifactuQrLector())->buscar($rutaAbsoluta);

            if ($verifactu) {
                $plantilla = PlantillaFactura::where('cif', $verifactu['cif'])->first();

                $resultados[] = array_merge($base, [
                    'con_plantilla' => true,
                    'verifactu'     => true,
                    'plantilla'     => [
                        'razon_social'   => $plantilla->razon_social ?? $datos['razon_social'],
                        'cif'            => $verifactu['cif'],
                        'numero_factura' => $verifactu['numero_factura'],
                        'fecha'          => $verifactu['fecha'],
                        // El QR de VeriFactu solo trae el importe total, sin desglose: sin
                        // base/cuotas no se puede dar de alta sola, queda para completar a mano.
                        'importe_total'  => $verifactu['importe'],
                        'importe_base'   => null,
                        'cuotas_iva'     => [],
                    ],
                ]);
            } else {
                $resultados[] = array_merge($base, $this->datosDePlantilla($texto, $datos['cif'], $datos['fecha'], $rutaAbsoluta));
            }
        }

        $this->dispatch('facturas-sociedad-procesadas', resultados: $resultados);
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
        $this->reset('facturas');
    }

    /**
     * Si el CIF detectado ya tiene plantilla, resuelve nº factura/fecha/base/total y las
     * cuotas de IVA por posición. Las cuotas cuya ancla no aparezca en ESTA factura
     * concreta simplemente no se localizan (no todas las facturas traen todas).
     */
    protected function datosDePlantilla(string $texto, ?string $cif, ?string $fechaGenerica = null, ?string $rutaAbsoluta = null): array
    {
        $plantilla = $cif ? PlantillaFactura::with('campos')->where('cif', $cif)->first() : null;

        if (! $plantilla && $rutaAbsoluta) {
            $hash = $this->hashImagenCabecera($rutaAbsoluta);
            $plantilla = $hash ? PlantillaFactura::with('campos')->where('hash_imagen', $hash)->first() : null;
        }

        if (! $plantilla) {
            return [];
        }

        $extractor = new ExtractorPosicional();
        $extractorPosicion = new ExtractorPorCoordenadas();
        $bloques   = null;
        $valores   = [];
        $cuotas    = [];

        $camposConEtiquetaValor = [
            TipoCampoPlantillaFactura::FECHA,
            TipoCampoPlantillaFactura::NUMERO_FACTURA,
            TipoCampoPlantillaFactura::IMPORTE_BASE,
            TipoCampoPlantillaFactura::IMPORTE_TOTAL,
        ];

        foreach ($plantilla->campos as $campo) {
            if ($campo->tipo_campo_plantilla_factura_id === TipoCampoPlantillaFactura::CUOTA_IVA) {
                $valor = $campo->pos_x !== null && $rutaAbsoluta
                    ? $extractorPosicion->buscarPorPosicion(
                        $bloques ??= LectorPdf::aBloquesConPosicion($rutaAbsoluta),
                        (float) $campo->pos_x, (float) $campo->pos_y, (float) $campo->pos_ancho, (int) $campo->pagina
                    )
                    : ($campo->delta_columna !== null
                        ? $extractor->buscarPorEtiquetaYDelta($texto, $campo->texto_ancla, $campo->delta_columna, $campo->delta_lineas, $campo->longitud_valor)
                        : $extractor->buscarPorAncla($texto, $campo->texto_ancla));

                // No está en esta factura: se omite sin más, no cuenta como incompleta por ello.
                if ($valor !== null) {
                    $cuotas[] = ['tipo_iva' => (float) $campo->tipo_iva, 'importe' => $valor];
                }

                continue;
            }

            if ($campo->pos_x !== null && $rutaAbsoluta) {
                $bloques ??= LectorPdf::aBloquesConPosicion($rutaAbsoluta);
                $valores[$campo->tipo_campo_plantilla_factura_id] = $extractorPosicion->buscarPorPosicion(
                    $bloques, (float) $campo->pos_x, (float) $campo->pos_y, (float) $campo->pos_ancho, (int) $campo->pagina
                );

                continue;
            }

            $valores[$campo->tipo_campo_plantilla_factura_id] = (in_array($campo->tipo_campo_plantilla_factura_id, $camposConEtiquetaValor, true) && $campo->delta_columna !== null)
                ? $extractor->buscarPorEtiquetaYDelta($texto, $campo->texto_ancla, $campo->delta_columna, $campo->delta_lineas, $campo->longitud_valor)
                : $extractor->buscarPorAncla($texto, $campo->texto_ancla);
        }

        return [
            'con_plantilla' => true,
            'plantilla'     => [
                'razon_social'   => $plantilla->razon_social,
                'cif'            => $plantilla->cif,
                'numero_factura' => $valores[TipoCampoPlantillaFactura::NUMERO_FACTURA] ?? null,
                'fecha'          => $valores[TipoCampoPlantillaFactura::FECHA] ?? $fechaGenerica,
                'importe_base'   => $valores[TipoCampoPlantillaFactura::IMPORTE_BASE] ?? null,
                'importe_total'  => $valores[TipoCampoPlantillaFactura::IMPORTE_TOTAL] ?? null,
                'cuotas_iva'     => $cuotas,
            ],
        ];
    }

    protected function hashImagenCabecera(string $rutaAbsoluta): ?string
    {
        $bytes = LectorPdf::extraerImagenPrincipal($rutaAbsoluta);

        return $bytes ? hash('sha256', $bytes) : null;
    }

    public function render()
    {
        return view('livewire.sociedades.proveedores.analizar-factura');
    }
}

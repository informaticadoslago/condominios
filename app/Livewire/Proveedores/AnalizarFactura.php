<?php

namespace App\Livewire\Proveedores;

use App\Models\Documento;
use App\Models\PlantillaFactura;
use App\Models\TipoCampoPlantillaFactura;
use App\Services\Facturas\ExtractorDatosFactura;
use App\Services\Facturas\LectorPdf;
use App\Services\Facturas\Plantillas\ExtractorPosicional;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

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

    #[On('abrir-analizar-factura')]
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

        foreach ($this->facturas as $factura) {
            $metadatos = Documento::subirFichero($factura, enBorrador: true);

            $ruta = ltrim(trim($metadatos['camino'], '/') . '/' . $metadatos['nombrefichero'], '/');
            $texto = LectorPdf::aTexto(Documento::disco()->path($ruta));
            $datos = (new ExtractorDatosFactura())->extraer($texto);

            $resultados[] = array_merge($metadatos, [
                'ruta'          => $ruta,
                'texto'         => $texto,
                'datos'         => $datos,
                'con_plantilla' => false,
            ], $this->datosDePlantilla($texto, $datos['cif']));
        }

        $this->dispatch('facturas-procesadas', resultados: $resultados);
        $this->cerrar();
    }

    public function cerrar()
    {
        $this->abrir = false;
        $this->reset('facturas');
    }

    /** Si el CIF detectado ya tiene plantilla, resuelve nº factura/fecha/importe por posición. */
    protected function datosDePlantilla(string $texto, ?string $cif): array
    {
        if (! $cif) {
            return [];
        }

        $plantilla = PlantillaFactura::with('campos')->where('cif', $cif)->first();
        if (! $plantilla) {
            return [];
        }

        $extractor = new ExtractorPosicional();
        $valores   = [];

        foreach ($plantilla->campos as $campo) {
            $valores[$campo->tipo_campo_plantilla_factura_id] = $extractor->buscarPorAncla($texto, $campo->texto_ancla);
        }

        return [
            'con_plantilla' => true,
            'plantilla'     => [
                'razon_social'   => $plantilla->razon_social,
                'cif'            => $plantilla->cif,
                'numero_factura' => $valores[TipoCampoPlantillaFactura::NUMERO_FACTURA] ?? null,
                'fecha'          => $valores[TipoCampoPlantillaFactura::FECHA] ?? null,
                'importe'        => $valores[TipoCampoPlantillaFactura::IMPORTE] ?? null,
            ],
        ];
    }

    public function render()
    {
        return view('livewire.proveedores.analizar-factura');
    }
}

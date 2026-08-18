<?php

namespace App\Livewire\Proveedores;

use App\Models\Comunidad;
use App\Models\Documento;
use App\Models\PlantillaFactura;
use App\Models\TipoCampoPlantillaFactura;
use App\Services\Facturas\ExtractorDatosFactura;
use App\Services\Facturas\LectorPdf;
use App\Services\Facturas\Plantillas\ExtractorPorCoordenadas;
use App\Services\Facturas\Plantillas\ExtractorPosicional;
use App\Services\Facturas\VerifactuQrLector;
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
        $cifComunidad = Comunidad::find(session('comunidad_actual_id'))?->cif;

        foreach ($this->facturas as $factura) {
            $metadatos = Documento::subirFichero($factura, enBorrador: true);

            $ruta         = ltrim(trim($metadatos['camino'], '/') . '/' . $metadatos['nombrefichero'], '/');
            $rutaAbsoluta = Documento::disco()->path($ruta);
            $texto        = LectorPdf::aTexto($rutaAbsoluta);
            $datos        = (new ExtractorDatosFactura())->extraer($texto, $cifComunidad);

            $base = array_merge($metadatos, [
                'ruta'          => $ruta,
                'texto'         => $texto,
                'datos'         => $datos,
                'con_plantilla' => false,
            ]);

            $verifactu = (new VerifactuQrLector())->buscar($rutaAbsoluta);

            if ($verifactu) {
                // El QR de VeriFactu no lleva razón social (Hacienda ya la tiene por el NIF):
                // si ya hay una plantilla guardada para este CIF, su razón social manda.
                $plantilla = PlantillaFactura::where('cif', $verifactu['cif'])->first();

                $resultados[] = array_merge($base, [
                    'con_plantilla' => true,
                    'verifactu'     => true,
                    'plantilla'     => [
                        'razon_social'   => $plantilla->razon_social ?? $datos['razon_social'],
                        'cif'            => $verifactu['cif'],
                        'numero_factura' => $verifactu['numero_factura'],
                        'fecha'          => $verifactu['fecha'],
                        'importe'        => $verifactu['importe'],
                    ],
                ]);
            } else {
                $resultados[] = array_merge($base, $this->datosDePlantilla($texto, $datos['cif'], $datos['fecha'], $rutaAbsoluta));
            }
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
    protected function datosDePlantilla(string $texto, ?string $cif, ?string $fechaGenerica = null, ?string $rutaAbsoluta = null): array
    {
        $plantilla = $cif ? PlantillaFactura::with('campos')->where('cif', $cif)->first() : null;

        // Sin CIF en el texto (proveedores cuya cabecera está "quemada" en una imagen, ver
        // ExtractorPorCoordenadas): se reconoce el proveedor por la huella de esa misma imagen,
        // idéntica en todas sus facturas.
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

        // Fecha/nº factura/importe cambian de una factura a otra: la etiqueta la eligió el
        // usuario a mano (no se adivinó por proximidad), así que se re-localiza aplicando el
        // desplazamiento guardado. El CIF es constante por proveedor y sigue con el mecanismo
        // de siempre (aunque en la práctica ni se usa: 'cif' abajo viene de $plantilla->cif).
        $camposConEtiquetaValor = [
            TipoCampoPlantillaFactura::FECHA,
            TipoCampoPlantillaFactura::NUMERO_FACTURA,
            TipoCampoPlantillaFactura::IMPORTE,
        ];

        foreach ($plantilla->campos as $campo) {
            // Sin etiqueta de texto (ver ExtractorPorCoordenadas): se ancló por posición en la
            // página en vez de por texto.
            if ($campo->pos_x !== null && $rutaAbsoluta) {
                $bloques ??= LectorPdf::aBloquesConPosicion($rutaAbsoluta);
                $valores[$campo->tipo_campo_plantilla_factura_id] = $extractorPosicion->buscarPorPosicion(
                    $bloques, (float) $campo->pos_x, (float) $campo->pos_y, (float) $campo->pos_ancho, (int) $campo->pagina
                );

                continue;
            }

            // Campos marcados antes de este cambio no tienen delta guardado: se tratan como
            // el mecanismo antiguo en vez de reventar.
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
                // La fecha no siempre tiene ancla propia (si se confirmó "usar detectado" sin
                // marcar, no queda posición guardada): en ese caso, mejor el genérico que nada.
                'fecha'          => $valores[TipoCampoPlantillaFactura::FECHA] ?? $fechaGenerica,
                'importe'        => $valores[TipoCampoPlantillaFactura::IMPORTE] ?? null,
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
        return view('livewire.proveedores.analizar-factura');
    }
}

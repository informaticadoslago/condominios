<?php

namespace App\Livewire\Facturas;

use App\Exceptions\DocumentoInvalidoException;
use App\Exceptions\FacturaDuplicadaException;
use App\Models\Comunidad;
use App\Models\Documento;
use App\Models\PlantillaFactura;
use App\Models\TipoCampoPlantillaFactura;
use App\Models\TipoProveedor;
use App\Services\Facturas\AltaProveedorDesdeFactura;
use App\Services\Facturas\ExtractorDatosFactura;
use App\Services\Facturas\LectorPdf;
use App\Services\Facturas\Plantillas\ExtractorPosicional;
use App\Services\Facturas\VerifactuQrLector;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Importación de facturas desde una carpeta local (con subcarpetas): el navegador
 * sube los PDF de uno en uno (evita los límites de subida del servidor con carpetas
 * grandes) y cada uno se analiza nada más llegar. Cuando no hay plantilla, se manda a
 * la ventana de "Analizar factura" de Proveedores (ya sabe crear proveedor/plantilla);
 * aquí solo se importa lo que ya viene resuelto del todo.
 */
class ImportarFacturas extends Component
{
    use WithFileUploads;

    public bool $abrir = false;

    public $fichero = null;

    /** Cuántos PDF ha anunciado el navegador para este lote (para la barra de progreso). */
    public int $total = 0;

    /** Resultado del análisis de cada PDF ya recibido, indexado por su posición de llegada. */
    public array $resultados = [];

    /** Tipo elegido para el proveedor de cada fila, indexado igual que $resultados. */
    public array $tipoProveedor = [];

    protected function rules()
    {
        return [
            'fichero' => 'file|mimes:pdf|max:10240',
        ];
    }

    #[On('abrir-importar-facturas')]
    public function mostrar()
    {
        $this->reset(['fichero', 'resultados', 'total']);
        $this->resetErrorBag();
        $this->abrir = true;
    }

    /** El navegador ya sabe cuántos PDF hay en la carpeta (tras filtrar por extensión): arranca la barra de progreso. */
    public function iniciarLote(int $total)
    {
        $this->total = $total;
        $this->resultados = [];
    }

    /** Se llama tras subir cada PDF: lo analiza y lo añade a la tabla de resultados. */
    public function procesarUno()
    {
        $this->validate();

        if (! $this->fichero) {
            return;
        }

        $cifComunidad = Comunidad::find(session('comunidad_actual_id'))?->cif;

        $this->resultados[] = $this->analizar($this->fichero, $cifComunidad);

        $this->reset('fichero');
    }

    protected function analizar($fichero, ?string $cifComunidad): array
    {
        $nombreLocal = $fichero->getClientOriginalName();
        $texto       = LectorPdf::aTexto($fichero->getRealPath());

        // Ni rastro de la palabra "factura": se ignora sin gastar nada en guardarlo ni analizarlo más.
        if (mb_stripos($texto, 'factura') === false) {
            return [
                'nombrelocal' => $nombreLocal,
                'es_factura'  => false,
            ];
        }

        $metadatos    = Documento::subirFichero($fichero, enBorrador: true);
        $ruta         = ltrim(trim($metadatos['camino'], '/') . '/' . $metadatos['nombrefichero'], '/');
        $rutaAbsoluta = Documento::disco()->path($ruta);
        $datos        = (new ExtractorDatosFactura())->extraer($texto, $cifComunidad);

        $base = array_merge($metadatos, [
            'ruta'          => $ruta,
            'texto'         => $texto,
            'datos'         => $datos,
            'es_factura'    => true,
            'con_plantilla' => false,
        ]);

        $verifactu = (new VerifactuQrLector())->buscar($rutaAbsoluta);

        $resultado = $verifactu
            ? array_merge($base, $this->datosDeVerifactu($verifactu, $datos))
            : array_merge($base, $this->datosDePlantilla($texto, $datos['cif'], $datos['fecha']));

        $resultado['duplicada'] = $this->esDuplicada($resultado);

        return $resultado;
    }

    protected function datosDeVerifactu(array $verifactu, array $datosGenericos): array
    {
        // El QR de VeriFactu no lleva razón social (Hacienda ya la tiene por el NIF): si ya
        // hay una plantilla guardada para este CIF, su razón social manda.
        $plantilla = PlantillaFactura::where('cif', $verifactu['cif'])->first();

        return [
            'con_plantilla' => true,
            'verifactu'     => true,
            'plantilla'     => [
                'razon_social'   => $plantilla->razon_social ?? $datosGenericos['razon_social'],
                'cif'            => $verifactu['cif'],
                'numero_factura' => $verifactu['numero_factura'],
                'fecha'          => $verifactu['fecha'],
                'importe'        => $verifactu['importe'],
            ],
        ];
    }

    /** Mismo mecanismo que Proveedores\AnalizarFactura::datosDePlantilla(): resuelve por posición si el CIF ya tiene plantilla. */
    protected function datosDePlantilla(string $texto, ?string $cif, ?string $fechaGenerica = null): array
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

        $camposConEtiquetaValor = [
            TipoCampoPlantillaFactura::FECHA,
            TipoCampoPlantillaFactura::NUMERO_FACTURA,
            TipoCampoPlantillaFactura::IMPORTE,
        ];

        foreach ($plantilla->campos as $campo) {
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
                'importe'        => $valores[TipoCampoPlantillaFactura::IMPORTE] ?? null,
            ],
        ];
    }

    protected function esDuplicada(array $resultado): bool
    {
        if (! ($resultado['con_plantilla'] ?? false)) {
            return false;
        }

        $plantilla = $resultado['plantilla'] ?? [];
        if (! ($plantilla['cif'] ?? null)) {
            return false;
        }

        return (new AltaProveedorDesdeFactura())->existeDuplicada(
            session('comunidad_actual_id'),
            $plantilla['cif'],
            $plantilla['numero_factura'] ?? null,
            $plantilla['fecha'] ?? null,
        );
    }

    /** Este PDF no tenía plantilla: se manda al mismo modal de Proveedores que ya sabe marcarla/generarla con IA. */
    public function analizarEnVentanaProveedores($indice)
    {
        $resultado = $this->resultados[$indice] ?? null;
        if (! $resultado) {
            return;
        }

        $this->dispatch('facturas-procesadas', resultados: [$resultado]);
    }

    /** Tras crear la plantilla en la otra ventana, se reintenta la extracción por posición con el texto que ya teníamos cacheado. */
    public function refrescarConPlantilla($indice)
    {
        $resultado = $this->resultados[$indice] ?? null;
        if (! $resultado || ! ($resultado['es_factura'] ?? false)) {
            return;
        }

        $encontrado = $this->datosDePlantilla($resultado['texto'], $resultado['datos']['cif'] ?? null, $resultado['datos']['fecha'] ?? null);

        if (! $encontrado) {
            $this->dispatch('toast-error', ['title' => __('Sigue sin haber plantilla para este CIF')]);

            return;
        }

        $resultado = array_merge($resultado, $encontrado);
        $resultado['duplicada'] = $this->esDuplicada($resultado);

        $this->resultados[$indice] = $resultado;
    }

    public function darDeAlta($indice, $sobrescribir = false)
    {
        $resultado = $this->resultados[$indice] ?? null;
        if (! $resultado) {
            return;
        }

        $plantilla = $resultado['plantilla'] ?? [];
        $documento = $plantilla['cif'] ?? null;

        if (! $documento) {
            $this->dispatch('toast-error', ['title' => __('No hay CIF/NIF para dar de alta el proveedor')]);

            return;
        }

        // Proveedor nuevo: sin saber a qué se dedica no hay cuenta de gasto para su factura.
        if (empty($this->tipoProveedor[$indice])
            && ! (new AltaProveedorDesdeFactura())->proveedorExiste((int) session('comunidad_actual_id'), $documento)) {
            $this->dispatch('toast-error', ['title' => __('Elija el tipo del proveedor')]);

            return;
        }

        $metadatosFichero = array_intersect_key($resultado, array_flip(['nombrefichero', 'nombrelocal', 'camino', 'extension', 'size']));

        try {
            $alta = (new AltaProveedorDesdeFactura())->ejecutar(
                session('comunidad_actual_id'),
                $documento,
                $plantilla['razon_social'] ?? null,
                $metadatosFichero,
                $plantilla['numero_factura'] ?? null,
                $plantilla['fecha'] ?? null,
                $plantilla['importe'] ?? null,
                (bool) $sobrescribir,
                tipoProveedorId: $this->tipoProveedor[$indice] ?? null,
            );
        } catch (DocumentoInvalidoException $e) {
            $this->dispatch('toast-error', ['title' => $e->getMessage()]);

            return;
        } catch (FacturaDuplicadaException $e) {
            // Puede pasar aunque no viniera marcada como duplicada: dos PDF del mismo lote
            // con el mismo nº/fecha, y el primero se acaba de importar. Se marca ahora y el
            // botón pasa a Sobrescribir/Descartar en vez de reventar.
            $this->resultados[$indice]['duplicada'] = true;
            $this->dispatch('toast-error', ['title' => $e->getMessage()]);

            return;
        }

        unset($this->resultados[$indice]);

        $this->dispatch('toast-success', [
            'title' => $alta['creado']
                ? __('Proveedor creado y factura adjuntada')
                : __('Factura adjuntada al proveedor existente'),
        ]);

        $this->dispatch('factura-importada');
    }

    /** Duplicada y no interesa: se quita de la lista sin tocar la que ya había. */
    public function descartar($indice)
    {
        unset($this->resultados[$indice]);
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    protected function datosCompletos(array $resultado): bool
    {
        if (! ($resultado['con_plantilla'] ?? false)) {
            return false;
        }

        $p = $resultado['plantilla'] ?? [];

        return filled($p['cif'] ?? null) && filled($p['fecha'] ?? null)
            && filled($p['numero_factura'] ?? null) && filled($p['importe'] ?? null);
    }

    public function render()
    {
        $completos   = [];
        $incompletos = [];
        $noFacturas  = [];

        $alta = new AltaProveedorDesdeFactura();

        foreach ($this->resultados as $indice => $resultado) {
            if (! ($resultado['es_factura'] ?? false)) {
                $noFacturas[$indice] = $resultado;
            } elseif ($this->datosCompletos($resultado)) {
                // Solo hay que preguntar el tipo del proveedor que todavía no existe.
                $resultado['proveedor_existe'] = $alta->proveedorExiste(
                    (int) session('comunidad_actual_id'),
                    $resultado['plantilla']['cif'] ?? null,
                );
                $completos[$indice] = $resultado;
            } else {
                $incompletos[$indice] = $resultado;
            }
        }

        return view('livewire.facturas.importar-facturas', [
            'completos'      => $completos,
            'incompletos'    => $incompletos,
            'noFacturas'     => $noFacturas,
            'tiposProveedor' => TipoProveedor::activo()->orderBy('descripcion')->get(),
        ]);
    }
}

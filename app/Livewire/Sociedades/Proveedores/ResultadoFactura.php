<?php

namespace App\Livewire\Sociedades\Proveedores;

use App\Exceptions\DocumentoInvalidoException;
use App\Exceptions\FacturaDuplicadaException;
use App\Exceptions\GeneracionPlantillaIAException;
use App\Models\Documento;
use App\Models\PlantillaFactura;
use App\Models\Sociedad;
use App\Models\TipoCampoPlantillaFactura;
use App\Models\TipoProveedorSociedad;
use App\Services\Facturas\AltaProveedorDesdeFactura;
use App\Services\Facturas\AltaProveedorDesdeFacturaSociedad;
use App\Services\Facturas\GeneradorPlantillaIA;
use Livewire\Attributes\On;
use Livewire\Component;

/** Igual que Proveedores\ResultadoFactura (comunidad), pero con base/cuotas de IVA/total en vez de un único importe. */
class ResultadoFactura extends Component
{
    public bool $abrir = false;

    public array $resultados = [];

    public array $tipoProveedor = [];

    /** Tolerancia de redondeo al comparar total contra base + suma de cuotas. */
    protected const TOLERANCIA_CENTIMOS = 0.01;

    #[On('facturas-sociedad-procesadas')]
    public function mostrar($resultados)
    {
        $this->resultados = $resultados;
        $this->abrir = true;
    }

    public function completarPlantilla($indice)
    {
        $resultado = $this->resultados[$indice] ?? null;
        if (! $resultado) {
            return;
        }

        $this->dispatch('abrir-marcar-plantilla-factura-sociedad',
            texto: $resultado['texto'],
            cif: $resultado['datos']['cif'] ?? null,
            razonSocial: $resultado['datos']['razon_social'] ?? null,
            fecha: $resultado['datos']['fecha'] ?? null,
            indice: $indice,
            rutaAbsoluta: $this->rutaAbsolutaDe($resultado),
        );
    }

    protected function rutaAbsolutaDe(array $resultado): ?string
    {
        return isset($resultado['ruta']) ? Documento::disco()->path($resultado['ruta']) : null;
    }

    public function generarPlantillaConIA($indice)
    {
        $resultado = $this->resultados[$indice] ?? null;
        if (! $resultado) {
            return;
        }

        $cifSociedad = Sociedad::find(session('sociedad_actual_id'))?->persona?->documento_identificativo;

        try {
            $generado = (new GeneradorPlantillaIA())->generarSociedad($resultado['texto'], $cifSociedad);
        } catch (GeneracionPlantillaIAException $e) {
            $this->dispatch('toast-error', ['title' => $e->getMessage()]);

            return;
        }

        $conPlantillaActual = $resultado['con_plantilla'] ?? false;
        $cif = $generado['cif'] ?? ($conPlantillaActual ? ($resultado['plantilla']['cif'] ?? null) : null);

        if (! $cif) {
            $this->dispatch('toast-error', ['title' => __('Sin CIF no se puede guardar la plantilla')]);

            return;
        }

        PlantillaFactura::guardarDesdeCampos($cif, $generado['razon_social'], $generado['campos']);

        $campos = $generado['campos'];
        $cuotas = $campos[TipoCampoPlantillaFactura::CUOTA_IVA] ?? [];

        $nuevoPlantilla = [
            'razon_social'   => $generado['razon_social'],
            'cif'            => $cif,
            'numero_factura' => $campos[TipoCampoPlantillaFactura::NUMERO_FACTURA]['valor'] ?? null,
            'fecha'          => $campos[TipoCampoPlantillaFactura::FECHA]['valor'] ?? null,
            'importe_base'   => $campos[TipoCampoPlantillaFactura::IMPORTE_BASE]['valor'] ?? null,
            'importe_total'  => $campos[TipoCampoPlantillaFactura::IMPORTE_TOTAL]['valor'] ?? null,
        ];
        if ($cuotas) {
            $nuevoPlantilla['cuotas_iva'] = array_map(fn ($c) => ['tipo_iva' => $c['tipo_iva'], 'importe' => $c['valor']], $cuotas);
        }

        $this->resultados[$indice]['con_plantilla'] = true;
        $this->resultados[$indice]['plantilla'] = array_merge(
            $resultado['plantilla'] ?? [],
            array_filter($nuevoPlantilla, fn ($valor) => $valor !== null)
        );

        $this->dispatch('toast-success', ['title' => __('Plantilla generada con IA')]);
    }

    public function borrarPlantilla($indice)
    {
        $resultado = $this->resultados[$indice] ?? null;
        $cif       = $resultado['plantilla']['cif'] ?? null;
        if (! $cif) {
            return;
        }

        $this->dispatch('swalConfirm', [
            'title'              => __('Borrar plantilla'),
            'text'               => __('¿Seguro que quieres borrar la plantilla de este proveedor? Habrá que volver a marcarla desde cero.'),
            'icon'               => 'warning',
            'showCancelButton'   => true,
            'confirmButtonColor' => '#d33',
            'cancelButtonColor'  => '#f1c40f',
            'confirmButtonText'  => __('Sí, borrar'),
            'cancelButtonText'   => __('Cancelar'),
            'confirmCallback'    => 'borrarPlantillaSociedadConfirmado',
            'cancelCallback'     => 'borrarPlantillaSociedadCancelado',
            'id'                 => $indice,
        ]);
    }

    #[On('borrarPlantillaSociedadConfirmado')]
    public function borrarPlantillaSociedadConfirmado($id)
    {
        $resultado = $this->resultados[$id] ?? null;
        $cif       = $resultado['plantilla']['cif'] ?? null;
        if (! $cif) {
            return;
        }

        PlantillaFactura::where('cif', $cif)->delete();

        $this->resultados[$id]['con_plantilla'] = false;
        unset($this->resultados[$id]['plantilla']);

        $this->dispatch('toast-success', ['title' => __('Plantilla borrada')]);
    }

    #[On('borrarPlantillaSociedadCancelado')]
    public function borrarPlantillaSociedadCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function corregirCampo($indice, $tipoCampo)
    {
        $resultado = $this->resultados[$indice] ?? null;
        if (! $resultado) {
            return;
        }

        $conPlantilla = $resultado['con_plantilla'] ?? false;
        $cif          = $conPlantilla ? ($resultado['plantilla']['cif'] ?? null) : ($resultado['datos']['cif'] ?? null);

        $this->dispatch('abrir-corregir-campo-plantilla-sociedad',
            texto: $resultado['texto'],
            cif: $cif,
            tipoCampo: $tipoCampo,
            indice: $indice,
            rutaAbsoluta: $this->rutaAbsolutaDe($resultado),
        );
    }

    #[On('plantilla-factura-sociedad-completada')]
    public function actualizarConPlantilla($indice, $valores)
    {
        if (! isset($this->resultados[$indice])) {
            return;
        }

        $vacio = ['razon_social' => null, 'cif' => null, 'numero_factura' => null, 'fecha' => null, 'importe_base' => null, 'importe_total' => null, 'cuotas_iva' => []];

        $this->resultados[$indice]['con_plantilla'] = true;
        $this->resultados[$indice]['plantilla'] = array_merge(
            $this->resultados[$indice]['plantilla'] ?? $vacio,
            array_intersect_key($valores, $vacio)
        );
    }

    /** Total == base + suma de cuotas encontradas (no hace falta que estén todas las que define la plantilla). */
    protected function cuadra(array $plantilla): bool
    {
        $base  = $plantilla['importe_base'] ?? null;
        $total = $plantilla['importe_total'] ?? null;
        if ($base === null || $total === null) {
            return false;
        }

        // normalizarImporte/normalizarFecha viven en la clase de comunidad y se reutilizan
        // tal cual (formato de importe/fecha es el mismo en los dos plugins).
        $normalizar = fn ($v) => (new AltaProveedorDesdeFactura())->normalizarImporte($v);
        $baseNum  = $normalizar($base);
        $totalNum = $normalizar($total);
        if ($baseNum === null || $totalNum === null) {
            return false;
        }

        $sumaCuotas = 0.0;
        foreach ($plantilla['cuotas_iva'] ?? [] as $cuota) {
            $sumaCuotas += $normalizar($cuota['importe'] ?? null) ?? 0;
        }

        return abs($totalNum - ($baseNum + $sumaCuotas)) <= self::TOLERANCIA_CENTIMOS;
    }

    public function darDeAlta($indice, $sobrescribir = false)
    {
        $resultado = $this->resultados[$indice] ?? null;
        if (! $resultado) {
            return;
        }

        $conPlantilla = $resultado['con_plantilla'] ?? false;
        $documento    = $conPlantilla ? ($resultado['plantilla']['cif'] ?? null) : ($resultado['datos']['cif'] ?? null);

        if (! $documento) {
            $this->dispatch('toast-error', ['title' => __('No hay CIF/NIF para dar de alta el proveedor')]);

            return;
        }

        $plantilla = $conPlantilla ? $resultado['plantilla'] : [];

        if ($conPlantilla && ! $this->cuadra($plantilla)) {
            $this->dispatch('toast-error', ['title' => __('El total no cuadra con la base más las cuotas de IVA: revisa los importes antes de dar de alta.')]);

            return;
        }

        $razonSocial   = $conPlantilla ? ($plantilla['razon_social'] ?? null) : ($resultado['datos']['razon_social'] ?? null);
        $numeroFactura = $plantilla['numero_factura'] ?? null;
        $fecha         = $conPlantilla ? ($plantilla['fecha'] ?? null) : ($resultado['datos']['fecha'] ?? null);
        $importeBase   = $plantilla['importe_base'] ?? null;
        $importeTotal  = $plantilla['importe_total'] ?? null;
        $cuotasIva     = $plantilla['cuotas_iva'] ?? [];

        if (empty($this->tipoProveedor[$indice])
            && ! (new AltaProveedorDesdeFacturaSociedad())->proveedorExiste((int) session('sociedad_actual_id'), $documento)) {
            $this->dispatch('toast-error', ['title' => __('Elija el tipo del proveedor')]);

            return;
        }

        $metadatosFichero = array_intersect_key($resultado, array_flip(['nombrefichero', 'nombrelocal', 'camino', 'extension', 'size']));

        try {
            $alta = (new AltaProveedorDesdeFacturaSociedad())->ejecutar(
                session('sociedad_actual_id'),
                $documento,
                $razonSocial,
                $metadatosFichero,
                $numeroFactura,
                $fecha,
                $importeBase,
                $importeTotal,
                $cuotasIva,
                (bool) $sobrescribir,
                tipoProveedorId: $this->tipoProveedor[$indice] ?? null,
            );
        } catch (DocumentoInvalidoException $e) {
            $this->dispatch('toast-error', ['title' => $e->getMessage()]);

            return;
        } catch (FacturaDuplicadaException $e) {
            $this->dispatch('swalConfirm', [
                'title'              => __('Factura ya existente'),
                'text'               => $e->getMessage() . ' ' . __('¿Quieres sobrescribirla?'),
                'icon'               => 'warning',
                'showCancelButton'   => true,
                'confirmButtonColor' => '#d33',
                'cancelButtonColor'  => '#f1c40f',
                'confirmButtonText'  => __('Sí, sobrescribir'),
                'cancelButtonText'   => __('Cancelar'),
                'confirmCallback'    => 'darDeAltaSociedadSobrescribiendo',
                'cancelCallback'     => 'darDeAltaSociedadCancelado',
                'id'                 => $indice,
            ]);

            return;
        }

        $this->resultados[$indice]['dado_de_alta'] = [
            'creado'    => $alta['creado'],
            'proveedor' => $alta['proveedor']->persona->nombreCompleto,
        ];

        $this->dispatch('toast-success', [
            'title' => $alta['creado']
                ? __('Proveedor creado y factura adjuntada')
                : __('Factura adjuntada al proveedor existente'),
        ]);

        $this->dispatch('proveedor-guardado');
    }

    #[On('darDeAltaSociedadSobrescribiendo')]
    public function darDeAltaSociedadSobrescribiendo($id)
    {
        $this->darDeAlta($id, sobrescribir: true);
    }

    #[On('darDeAltaSociedadCancelado')]
    public function darDeAltaSociedadCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        $alta            = new AltaProveedorDesdeFacturaSociedad();
        $sociedadId      = (int) session('sociedad_actual_id');
        $proveedorExiste = [];

        foreach ($this->resultados as $indice => $resultado) {
            $documento = ($resultado['con_plantilla'] ?? false)
                ? ($resultado['plantilla']['cif'] ?? null)
                : ($resultado['datos']['cif'] ?? null);

            $proveedorExiste[$indice] = $alta->proveedorExiste($sociedadId, $documento);
        }

        return view('livewire.sociedades.proveedores.resultado-factura', [
            'tiposProveedor'  => TipoProveedorSociedad::activo()->orderBy('descripcion')->get(),
            'proveedorExiste' => $proveedorExiste,
        ]);
    }
}

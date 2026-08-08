<?php

namespace App\Livewire\Proveedores;

use App\Exceptions\DocumentoInvalidoException;
use App\Exceptions\FacturaDuplicadaException;
use App\Exceptions\GeneracionPlantillaIAException;
use App\Models\Comunidad;
use App\Models\PlantillaFactura;
use App\Models\TipoCampoPlantillaFactura;
use App\Models\TipoProveedor;
use App\Services\Facturas\AltaProveedorDesdeFactura;
use App\Services\Facturas\GeneradorPlantillaIA;
use Livewire\Attributes\On;
use Livewire\Component;

class ResultadoFactura extends Component
{
    public bool $abrir = false;

    public array $resultados = [];

    /** Tipo elegido para el proveedor de cada resultado, indexado igual que $resultados. */
    public array $tipoProveedor = [];

    #[On('facturas-procesadas')]
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

        $this->dispatch('abrir-marcar-plantilla-factura',
            texto: $resultado['texto'],
            cif: $resultado['datos']['cif'] ?? null,
            razonSocial: $resultado['datos']['razon_social'] ?? null,
            fecha: $resultado['datos']['fecha'] ?? null,
            indice: $indice,
        );
    }

    /**
     * Genera (o regenera) la plantilla llamando a la API de Claude para que localice
     * los valores en el texto, igual que haría un humano marcando con el ratón — no
     * se usa para extraer los datos de cada factura, solo para construir la plantilla
     * una vez por proveedor (ver GeneradorPlantillaIA).
     */
    public function generarPlantillaConIA($indice)
    {
        $resultado = $this->resultados[$indice] ?? null;
        if (! $resultado) {
            return;
        }

        $cifComunidad = Comunidad::find(session('comunidad_actual_id'))?->cif;

        try {
            $generado = (new GeneradorPlantillaIA())->generar($resultado['texto'], $cifComunidad);
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

        $nuevoPlantilla = [
            'razon_social'   => $generado['razon_social'],
            'cif'            => $cif,
            'numero_factura' => $campos[TipoCampoPlantillaFactura::NUMERO_FACTURA]['valor'] ?? null,
            'fecha'          => $campos[TipoCampoPlantillaFactura::FECHA]['valor'] ?? null,
            'importe'        => $campos[TipoCampoPlantillaFactura::IMPORTE]['valor'] ?? null,
        ];

        $this->resultados[$indice]['con_plantilla'] = true;
        $this->resultados[$indice]['plantilla'] = array_merge(
            $resultado['plantilla'] ?? [],
            array_filter($nuevoPlantilla, fn ($valor) => $valor !== null)
        );

        $this->dispatch('toast-success', ['title' => __('Plantilla generada con IA')]);
    }

    /** Borra del todo la plantilla de este proveedor (todos sus campos) para volver a marcarla de cero. */
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
            'confirmCallback'    => 'borrarPlantillaConfirmado',
            'cancelCallback'     => 'borrarPlantillaCancelado',
            'id'                 => $indice,
        ]);
    }

    #[On('borrarPlantillaConfirmado')]
    public function borrarPlantillaConfirmado($id)
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

    #[On('borrarPlantillaCancelado')]
    public function borrarPlantillaCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    /** Ya hay plantilla para este proveedor, pero un campo concreto salió mal: se corrige solo ese. */
    public function corregirCampo($indice, $tipoCampo)
    {
        $resultado = $this->resultados[$indice] ?? null;
        if (! $resultado) {
            return;
        }

        $conPlantilla = $resultado['con_plantilla'] ?? false;
        $cif          = $conPlantilla ? ($resultado['plantilla']['cif'] ?? null) : ($resultado['datos']['cif'] ?? null);

        $this->dispatch('abrir-corregir-campo-plantilla',
            texto: $resultado['texto'],
            cif: $cif,
            tipoCampo: $tipoCampo,
            indice: $indice,
        );
    }

    #[On('plantilla-factura-completada')]
    public function actualizarConPlantilla($indice, $valores)
    {
        if (! isset($this->resultados[$indice])) {
            return;
        }

        $vacio = ['razon_social' => null, 'cif' => null, 'numero_factura' => null, 'fecha' => null, 'importe' => null];

        $this->resultados[$indice]['con_plantilla'] = true;
        $this->resultados[$indice]['plantilla'] = array_merge(
            $this->resultados[$indice]['plantilla'] ?? $vacio,
            array_intersect_key($valores, $vacio)
        );
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

        $razonSocial   = $conPlantilla ? ($resultado['plantilla']['razon_social'] ?? null) : ($resultado['datos']['razon_social'] ?? null);
        $numeroFactura = $resultado['plantilla']['numero_factura'] ?? null;
        $fecha         = $conPlantilla ? ($resultado['plantilla']['fecha'] ?? null) : ($resultado['datos']['fecha'] ?? null);
        $importe       = $resultado['plantilla']['importe'] ?? null;

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
                $razonSocial,
                $metadatosFichero,
                $numeroFactura,
                $fecha,
                $importe,
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
                'confirmCallback'    => 'darDeAltaSobrescribiendo',
                'cancelCallback'     => 'darDeAltaCancelado',
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

    #[On('darDeAltaSobrescribiendo')]
    public function darDeAltaSobrescribiendo($id)
    {
        $this->darDeAlta($id, sobrescribir: true);
    }

    #[On('darDeAltaCancelado')]
    public function darDeAltaCancelado($id = null)
    {
        // el usuario canceló; no hacemos nada
    }

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        // Solo hay que preguntar el tipo del proveedor que todavía no existe.
        $alta            = new AltaProveedorDesdeFactura();
        $comunidadId     = (int) session('comunidad_actual_id');
        $proveedorExiste = [];

        foreach ($this->resultados as $indice => $resultado) {
            $documento = ($resultado['con_plantilla'] ?? false)
                ? ($resultado['plantilla']['cif'] ?? null)
                : ($resultado['datos']['cif'] ?? null);

            $proveedorExiste[$indice] = $alta->proveedorExiste($comunidadId, $documento);
        }

        return view('livewire.proveedores.resultado-factura', [
            'tiposProveedor'  => TipoProveedor::activo()->orderBy('descripcion')->get(),
            'proveedorExiste' => $proveedorExiste,
        ]);
    }
}

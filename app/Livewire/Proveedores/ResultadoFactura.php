<?php

namespace App\Livewire\Proveedores;

use App\Exceptions\DocumentoInvalidoException;
use App\Services\Facturas\AltaProveedorDesdeFactura;
use Livewire\Attributes\On;
use Livewire\Component;

class ResultadoFactura extends Component
{
    public bool $abrir = false;

    public array $resultados = [];

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
            indice: $indice,
        );
    }

    /** Ya hay plantilla para este proveedor, pero un campo concreto salió mal: se corrige solo ese. */
    public function corregirCampo($indice, $tipoCampo)
    {
        $resultado = $this->resultados[$indice] ?? null;
        if (! $resultado) {
            return;
        }

        $this->dispatch('abrir-corregir-campo-plantilla',
            texto: $resultado['texto'],
            cif: $resultado['datos']['cif'] ?? null,
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

    public function darDeAlta($indice)
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

        $metadatosFichero = array_intersect_key($resultado, array_flip(['nombrefichero', 'nombrelocal', 'camino', 'extension', 'size']));

        try {
            $alta = (new AltaProveedorDesdeFactura())->ejecutar(
                session('comunidad_actual_id'),
                $documento,
                $razonSocial,
                $metadatosFichero,
                $numeroFactura,
                $fecha,
            );
        } catch (DocumentoInvalidoException $e) {
            $this->dispatch('toast-error', ['title' => $e->getMessage()]);

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

    public function cerrar()
    {
        $this->abrir = false;
    }

    public function render()
    {
        return view('livewire.proveedores.resultado-factura');
    }
}

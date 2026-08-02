<?php

namespace App\Livewire\Facturas;

use App\Livewire\ListaComponent;
use App\Models\Documento;
use App\Models\FacturaProveedor;
use App\Services\Facturas\AltaProveedorDesdeFactura;
use App\Services\Facturas\LectorPdf;
use Livewire\Attributes\On;

class Lista extends ListaComponent
{
    public function mount()
    {
        $this->sort      = 'id';
        $this->direction = 'desc';
    }

    #[On('factura-importada')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    /**
     * id de la factura que se está corrigiendo ahora mismo. MarcarPlantillaFactura emite
     * "plantilla-factura-completada" con el mismo nombre tanto si la abrió esta lista como
     * si la abrió Proveedores\ResultadoFactura (incluida en esta misma página desde
     * Importar): sin esta guarda, un "indice" que en realidad es la posición dentro de un
     * lote de importación podría confundirse con el id de una factura cualquiera.
     */
    public ?int $facturaEnCorreccion = null;

    /**
     * Una factura ya importada tiene un dato mal (p.ej. la fecha, si el PDF la escribe en
     * letra y no coincidía con la plantilla): se relee el PDF ya guardado y se manda al
     * mismo asistente de marcado que usa Proveedores, pero solo para ESE campo.
     */
    public function corregirCampo($facturaId, $tipoCampo)
    {
        $factura = FacturaProveedor::with(['documento', 'proveedor.persona'])->find($facturaId);
        if (! $factura || ! $factura->documento) {
            return;
        }

        if (! $factura->documento->existeFichero()) {
            $this->dispatch('toast-error', ['title' => __('El PDF de esta factura ya no está en el disco: no se puede releer.')]);

            return;
        }

        $texto = LectorPdf::aTexto(Documento::disco()->path($factura->documento->ruta));
        $this->facturaEnCorreccion = $factura->id;

        $this->dispatch('abrir-corregir-campo-plantilla',
            texto: $texto,
            cif: $factura->proveedor->persona->documento_identificativo,
            tipoCampo: (int) $tipoCampo,
            indice: $factura->id,
        );
    }

    /**
     * MarcarPlantillaFactura::guardarPlantilla() manda esto tras corregir el campo: además
     * de guardar la plantilla (arregla las próximas facturas de este proveedor), aquí se
     * actualiza YA la fila concreta que se estaba corrigiendo.
     */
    #[On('plantilla-factura-completada')]
    public function actualizarFacturaCorregida($indice, $valores)
    {
        if ($indice !== $this->facturaEnCorreccion) {
            return;
        }
        $this->facturaEnCorreccion = null;

        $factura = FacturaProveedor::find($indice);
        if (! $factura) {
            return;
        }

        $alta = new AltaProveedorDesdeFactura();

        if (array_key_exists('fecha', $valores)) {
            $factura->fecha_factura = $alta->normalizarFecha($valores['fecha']);
        }
        if (array_key_exists('numero_factura', $valores)) {
            $factura->numero_factura = $valores['numero_factura'];
        }
        if (array_key_exists('importe', $valores)) {
            $factura->importe = $alta->normalizarImporte($valores['importe']);
        }

        $factura->save();

        $this->dispatch('toast-success', ['title' => __('Factura corregida')]);
    }

    protected function filtroCif(): array
    {
        return [
            'clave'    => 'cif',
            'etiqueta' => __('CIF'),
            'tipo'     => 'texto',
            'aplicar'  => fn ($query, $valor) => $query->whereHas(
                'proveedor.persona',
                fn ($p) => $p->where('documento_identificativo', 'like', "%{$valor}%")
            ),
        ];
    }

    protected function filtroRazonSocial(): array
    {
        return [
            'clave'    => 'razon_social',
            'etiqueta' => __('Razón social'),
            'tipo'     => 'texto',
            'aplicar'  => fn ($query, $valor) => $query->whereHas(
                'proveedor.persona',
                fn ($p) => $p->where('razon_social', 'like', "%{$valor}%")
            ),
        ];
    }

    protected function filtroFechaDesde(): array
    {
        return [
            'clave'    => 'fecha_factura_desde',
            'etiqueta' => __('Fecha desde'),
            'tipo'     => 'fecha',
            // fecha_factura se guarda como texto dd/mm/aaaa (ver AltaProveedorDesdeFactura::normalizarFecha),
            // así que hay que reordenarla a aaaa-mm-dd para poder compararla con el rango.
            'aplicar'  => fn ($query, $valor) => $query->whereRaw("STR_TO_DATE(fecha_factura, '%d/%m/%Y') >= ?", [$valor]),
        ];
    }

    protected function filtroFechaHasta(): array
    {
        return [
            'clave'    => 'fecha_factura_hasta',
            'etiqueta' => __('Fecha hasta'),
            'tipo'     => 'fecha',
            'aplicar'  => fn ($query, $valor) => $query->whereRaw("STR_TO_DATE(fecha_factura, '%d/%m/%Y') <= ?", [$valor]),
        ];
    }

    public function definicionesFiltro(): array
    {
        return [
            $this->filtroCif(),
            $this->filtroRazonSocial(),
            $this->filtroFechaDesde(),
            $this->filtroFechaHasta(),
        ];
    }

    public function columnasDisponibles(): array
    {
        return [
            'cif'            => __('CIF proveedor'),
            'razon_social'   => __('Razón social'),
            'fecha_factura'  => __('Fecha factura'),
            'numero_factura' => __('Número factura'),
            'importe'        => __('Importe'),
        ];
    }

    public function render()
    {
        $search = trim($this->search ?? '');

        $items = $this->aplicarFiltros(
            FacturaProveedor::with('proveedor.persona')
                ->whereHas('proveedor.persona', fn ($p) => $p->where('comunidad_id', session('comunidad_actual_id')))
        )
            ->when($search, function ($q) use ($search) {
                // Agrupado en un where anidado: si no, el orWhereHas de dentro se
                // desengancha del whereHas de comunidad de arriba y se ve gente de otras.
                $q->where(function ($q2) use ($search) {
                    $q2->where('numero_factura', 'like', "%{$search}%")
                        ->orWhereHas('proveedor.persona', fn ($p) => $p
                            ->where('razon_social', 'like', "%{$search}%")
                            ->orWhere('documento_identificativo', 'like', "%{$search}%"));
                });
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->lineasXPagina);

        return view('livewire.facturas.lista', compact('items'));
    }
}

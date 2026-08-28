<?php

namespace App\Livewire\Sociedades\Facturas;

use App\Livewire\ListaComponent;
use App\Models\FacturaProveedorSociedad;
use App\Models\PersonaSociedad;
use Livewire\Attributes\On;

/**
 * Facturas de proveedor ya dadas de alta en la sociedad (ver Sociedades\Proveedores\
 * AnalizarFactura/ResultadoFactura, que es quien las crea). Solo lista, de momento: sin
 * pago ni contabilizar (eso queda para después, ver [[project-facturas-contabilidad]]).
 */
class Lista extends ListaComponent
{
    public function mount()
    {
        $this->sort      = 'id';
        $this->direction = 'desc';
    }

    #[On('proveedor-guardado')]
    public function refrescar()
    {
        // el evento fuerza el re-render de la lista
    }

    protected function filtroCif(): array
    {
        return [
            'clave'    => 'cif',
            'etiqueta' => __('CIF'),
            'tipo'     => 'texto',
            'aplicar'  => fn ($query, $valor) => $query->whereHas(
                'proveedor',
                fn ($q) => $q->whereHasMorph('persona', [PersonaSociedad::class],
                    fn ($p) => $p->where('documento_identificativo', 'like', "%{$valor}%"))
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
                'proveedor',
                fn ($q) => $q->whereHasMorph('persona', [PersonaSociedad::class], fn ($p) => $p
                    ->where('razon_social', 'like', "%{$valor}%")
                    ->orWhere('nombre', 'like', "%{$valor}%")
                    ->orWhere('apellido1', 'like', "%{$valor}%")
                    ->orWhere('apellido2', 'like', "%{$valor}%"))
            ),
        ];
    }

    protected function filtroFechaDesde(): array
    {
        return [
            'clave'    => 'fecha_factura_desde',
            'etiqueta' => __('Fecha desde'),
            'tipo'     => 'fecha',
            // fecha_factura se guarda como texto dd/mm/aaaa (ver AltaProveedorDesdeFacturaSociedad::normalizarFecha).
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
            'importe_base'   => __('Base imponible'),
            'importe_total'  => __('Importe total'),
        ];
    }

    /** cif y razon_social viven en proveedor.persona (pedirían join); el resto se ordena tal cual. */
    protected function columnasOrdenables(): ?array
    {
        return ['id', 'fecha_factura', 'importe_base', 'importe_total'];
    }

    private function consultaBase()
    {
        $search = trim($this->search ?? '');

        return FacturaProveedorSociedad::with(['proveedor.persona', 'documento', 'cuotasIva'])
            ->whereHas('proveedor', fn ($q) => $q->deSociedad(session('sociedad_actual_id')))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('numero_factura', 'like', "%{$search}%")
                        ->orWhereHas('proveedor', fn ($q3) => $q3->whereHasMorph('persona', [PersonaSociedad::class], fn ($p) => $p
                            ->where('razon_social', 'like', "%{$search}%")
                            ->orWhere('nombre', 'like', "%{$search}%")
                            ->orWhere('apellido1', 'like', "%{$search}%")
                            ->orWhere('apellido2', 'like', "%{$search}%")
                            ->orWhere('documento_identificativo', 'like', "%{$search}%")));
                });
            });
    }

    public function render()
    {
        $items = $this->aplicarFiltros($this->consultaBase())
            // fecha_factura se guarda como texto dd/mm/aaaa: ordenar tal cual sería
            // alfabético, no cronológico (ver filtroFechaDesde).
            ->when($this->sort === 'fecha_factura', fn ($q) => $q->orderByRaw(
                "STR_TO_DATE(fecha_factura, '%d/%m/%Y') {$this->direction}"
            ))
            ->when($this->sort !== 'fecha_factura', fn ($q) => $q->orderBy($this->sort, $this->direction))
            ->paginate($this->lineasXPagina);

        return view('livewire.sociedades.facturas.lista', ['items' => $items]);
    }
}

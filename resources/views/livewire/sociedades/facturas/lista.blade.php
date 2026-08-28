<x-botonera-page>
    <x-slot name="title">
        {{ __('Facturas') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Facturas de proveedores de la sociedad') }}
    </x-slot>
    <x-slot name="botonera">
        <x-button type="button" class="btn btn-nuevo" id="btn-nueva-factura-sociedad"
            wire:click="$dispatch('abrir-nueva-factura-sociedad')" title="{{ __('Nuevo') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
        </x-button>
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            <x-slot name="botonera">
                <x-secondary-button type="button" id="btn-analizar-factura-sociedad-facturas"
                    wire:click="$dispatch('abrir-analizar-factura-sociedad')" title="{{ __('Analizar factura') }}">
                    <i class="fa-solid fa-file-invoice mr-1"></i>{{ __('Analizar factura') }}
                </x-secondary-button>
                @include('livewire.parciales.selector-columnas')
                <x-secondary-button type="button" wire:click="borrarFiltro" title="{{ __('Borrar filtro') }}">
                    <i class="fa-solid fa-filter-circle-xmark mr-1"></i>{{ __('Borrar filtro') }}
                </x-secondary-button>
            </x-slot>

            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => "Proveedor o número de factura"])
            </div>
            @include('livewire.parciales.filtros')
            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            @foreach ($this->columnas as $clave)
                                @switch($clave)
                                    @case('cif')
                                        <th class="py-3 px-6">{{ __('CIF proveedor') }}</th>
                                        @break
                                    @case('razon_social')
                                        <th class="py-3 px-6">{{ __('Razón social') }}</th>
                                        @break
                                    @case('fecha_factura')
                                        <th class="cursor-pointer py-3 px-6" wire:click="ordenar('fecha_factura')">
                                            {{ __('Fecha factura') }}
                                            @if ($sort == 'fecha_factura')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
                                        @break
                                    @case('numero_factura')
                                        <th class="py-3 px-6">{{ __('Número factura') }}</th>
                                        @break
                                    @case('importe_base')
                                        <th class="cursor-pointer py-3 px-6" wire:click="ordenar('importe_base')">
                                            {{ __('Base imponible') }}
                                            @if ($sort == 'importe_base')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
                                        @break
                                    @case('importe_total')
                                        <th class="cursor-pointer py-3 px-6" wire:click="ordenar('importe_total')">
                                            {{ __('Importe total') }}
                                            @if ($sort == 'importe_total')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
                                        @break
                                @endswitch
                            @endforeach
                            <th class="py-3 px-6">{{ __('IVA') }}</th>
                            <th class="py-3 px-6 text-center">{{ __('Soporte') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                @foreach ($this->columnas as $clave)
                                    @switch($clave)
                                        @case('cif')
                                            <td class="px-6 py-4">{{ $item->proveedor->persona->documento_identificativo ?? '' }}</td>
                                            @break
                                        @case('razon_social')
                                            <td class="px-6 py-4">
                                                <span class="mayusculas">{{ $item->proveedor->persona->nombreCompleto ?? '' }}</span>
                                            </td>
                                            @break
                                        @case('fecha_factura')
                                            <td class="px-6 py-4">{{ $item->fecha_factura }}</td>
                                            @break
                                        @case('numero_factura')
                                            <td class="px-6 py-4">{{ $item->numero_factura }}</td>
                                            @break
                                        @case('importe_base')
                                            <td class="px-6 py-4">
                                                @if ($item->importe_base !== null)
                                                    {{ number_format($item->importe_base, 2, ',', '.') }} €
                                                @endif
                                            </td>
                                            @break
                                        @case('importe_total')
                                            <td class="px-6 py-4">
                                                @if ($item->importe_total !== null)
                                                    {{ number_format($item->importe_total, 2, ',', '.') }} €
                                                @endif
                                            </td>
                                            @break
                                    @endswitch
                                @endforeach
                                <td class="px-6 py-4">
                                    @forelse ($item->cuotasIva as $cuota)
                                        <span class="inline-block border rounded px-2 py-0.5 mr-1 mb-1 text-xs">{{ $cuota->tipo_iva }}% : {{ number_format($cuota->importe, 2, ',', '.') }} €</span>
                                    @empty
                                        <span class="text-gray-400">—</span>
                                    @endforelse
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if ($item->documento)
                                        <a href="{{ route('documentos.ver', $item->documento) }}" target="_blank"
                                            class="text-gray-500 hover:text-gray-800" title="{{ __('Ver') }}">
                                            <i class="fa-solid fa-eye text-lg"></i>
                                        </a>
                                    @else
                                        <span class="text-gray-400">{{ __('Sin soporte') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($items->hasPages())
                    <div class="px-6 py-3">
                        {{ $items->links() }}
                    </div>
                @endif
            @else
                <div class="py-3 px-6">{{ __('No se encontraron resultados.') }}</div>
            @endif
        </x-dosl.tabla>

        @livewire('sociedades.facturas.formulario')
        @livewire('sociedades.proveedores.analizar-factura')
        @livewire('sociedades.proveedores.resultado-factura')
        @livewire('sociedades.proveedores.marcar-plantilla-factura')
    </x-slot>
</x-botonera-page>

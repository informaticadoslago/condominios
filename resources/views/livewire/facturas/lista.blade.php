<x-botonera-page>
    <x-slot name="title">
        {{ __('Facturas') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Facturas de proveedores de la comunidad') }}
    </x-slot>
    <x-slot name="botonera">
        <x-button type="button" class="btn" id="btn-importar-facturas"
            wire:click="$dispatch('abrir-importar-facturas')" title="{{ __('Importar') }}">
            <i class="fa-solid fa-folder-open"> </i>{{ __('Importar') }}
        </x-button>
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            <x-slot name="botonera">
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
                            @if ($this->verColumna('cif'))
                                <th class="py-3 px-6">{{ __('CIF proveedor') }}</th>
                            @endif
                            @if ($this->verColumna('razon_social'))
                                <th class="py-3 px-6">{{ __('Razón social') }}</th>
                            @endif
                            @if ($this->verColumna('fecha_factura'))
                                <th class="py-3 px-6">{{ __('Fecha factura') }}</th>
                            @endif
                            @if ($this->verColumna('numero_factura'))
                                <th class="py-3 px-6">{{ __('Número factura') }}</th>
                            @endif
                            @if ($this->verColumna('importe'))
                                <th class="py-3 px-6">{{ __('Importe') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                @if ($this->verColumna('cif'))
                                    <td class="px-6 py-4">{{ $item->proveedor->persona->documento_identificativo ?? '' }}</td>
                                @endif
                                @if ($this->verColumna('razon_social'))
                                    <td class="px-6 py-4">
                                        <span class="mayusculas">{{ $item->proveedor->persona->razon_social ?? '' }}</span>
                                    </td>
                                @endif
                                @if ($this->verColumna('fecha_factura'))
                                    <td class="px-6 py-4">
                                        {{ $item->fecha_factura }}
                                        <button type="button" class="text-gray-400 hover:text-gray-800 ml-1" title="{{ __('Corregir fecha') }}"
                                            wire:click="corregirCampo({{ $item->id }}, {{ \App\Models\TipoCampoPlantillaFactura::FECHA }})">
                                            <i class="fa-solid fa-pen text-xs"></i>
                                        </button>
                                    </td>
                                @endif
                                @if ($this->verColumna('numero_factura'))
                                    <td class="px-6 py-4">
                                        {{ $item->numero_factura }}
                                        <button type="button" class="text-gray-400 hover:text-gray-800 ml-1" title="{{ __('Corregir número') }}"
                                            wire:click="corregirCampo({{ $item->id }}, {{ \App\Models\TipoCampoPlantillaFactura::NUMERO_FACTURA }})">
                                            <i class="fa-solid fa-pen text-xs"></i>
                                        </button>
                                    </td>
                                @endif
                                @if ($this->verColumna('importe'))
                                    <td class="px-6 py-4">
                                        @if ($item->importe !== null)
                                            {{ number_format($item->importe, 2, ',', '.') }} €
                                        @endif
                                        <button type="button" class="text-gray-400 hover:text-gray-800 ml-1" title="{{ __('Corregir importe') }}"
                                            wire:click="corregirCampo({{ $item->id }}, {{ \App\Models\TipoCampoPlantillaFactura::IMPORTE }})">
                                            <i class="fa-solid fa-pen text-xs"></i>
                                        </button>
                                    </td>
                                @endif
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

        @livewire('facturas.importar-facturas')
        @livewire('proveedores.resultado-factura')
        @livewire('proveedores.marcar-plantilla-factura')
    </x-slot>
</x-botonera-page>

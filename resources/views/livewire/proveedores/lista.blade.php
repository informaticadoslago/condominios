<x-botonera-page>
    <x-slot name="title">
        {{ __('Proveedores') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Proveedores de la comunidad') }}
    </x-slot>
    <x-slot name="botonera">
        <x-button type="button" class="btn btn-nuevo" id="btn-nuevo-proveedor"
            wire:click="$dispatch('abrir-crear-proveedor')" title="{{ __('Nuevo') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
        </x-button>
        <x-button type="button" class="btn ml-1" id="btn-analizar-factura"
            wire:click="$dispatch('abrir-analizar-factura')" title="{{ __('Analizar factura') }}">
            <i class="fa-solid fa-file-invoice"> </i>{{ __('Analizar factura') }}
        </x-button>
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => "Nombre o documento"])
            </div>
            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-3 px-6">{{ __('Nombre') }}</th>
                            <th class="py-3 px-6">{{ __('Documento') }}</th>
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                <td class="px-6 py-4">
                                    <span class="mayusculas">{{ $item->persona->nombreCompleto ?? '' }}</span>
                                </td>
                                <td class="px-6 py-4">{{ $item->persona->documento_identificativo ?? '' }}</td>
                                <td class="px-4 whitespace-nowrap">
                                    <x-button type="button" class="btn" id="btn-ver-proveedor-{{ $item->id }}"
                                        wire:click="$dispatch('proveedor-ver', {id: {{ $item->id }}})"
                                        title="{{ __('Ver') }}">
                                        <i class="fa-solid fa-eye"> </i>
                                    </x-button>
                                    <x-button type="button" class="btn-editar ml-1" id="btn-editar-proveedor-{{ $item->id }}"
                                        wire:click="$dispatch('proveedor-editar', {id: {{ $item->id }}})"
                                        title="{{ __('Modificar') }}">
                                        <i class="fa-solid fa-pen"> </i>
                                    </x-button>
                                    <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white ml-1"
                                        wire:click="confirmarEliminar({{ $item->id }})" title="{{ __('Eliminar') }}">
                                        <i class="fa-solid fa-trash"> </i>
                                    </x-button>
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

        @livewire('proveedores.ver')
        @livewire('proveedores.formulario')
        @livewire('proveedores.analizar-factura')
        @livewire('proveedores.resultado-factura')
        @livewire('proveedores.marcar-plantilla-factura')
    </x-slot>
</x-botonera-page>

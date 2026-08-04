<x-botonera-page>
    <x-slot name="title">
        {{ __('Empresas contables') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Gestión contable — independiente de comunidades, identificada por CIF') }}
    </x-slot>
    <x-slot name="botonera">
        <x-button type="button" class="btn btn-nuevo" id="btn-nuevo-empresa-contable"
            wire:click="$dispatch('abrir-crear-empresa-contable')" title="{{ __('Nueva') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nueva') }}
        </x-button>
    </x-slot>

    <x-slot name="content">
        @if ($empresaContableActualId)
            <div class="py-2 px-6">
                <x-button type="button" href="{{ route('empresa-contable.salir') }}"
                    class="bg-gray-600 hover:bg-gray-700 text-white" title="{{ __('Salir de la empresa contable') }}">
                    <i class="fa-solid fa-xmark"> </i> {{ __('Salir de la empresa contable activa') }}
                </x-button>
            </div>
        @endif

        <x-dosl.tabla>
            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => 'Razón social o CIF'])
            </div>
            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-3 px-6">{{ __('ID') }}</th>
                            <th class="py-3 px-6">{{ __('Razón social') }}</th>
                            <th class="py-3 px-6">{{ __('CIF') }}</th>
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}"
                                class="{{ $item->id == $empresaContableActualId ? 'bg-blue-50' : '' }}">
                                <td class="px-6 py-4">{{ $item->id }}</td>
                                <td class="px-6 py-4">
                                    <span class="mayusculas">{{ $item->razon_social }}</span>
                                    @if ($item->id == $empresaContableActualId)
                                        <span class="ml-2 text-xs text-blue-700">({{ __('activa') }})</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">{{ $item->cif }}</td>
                                <td class="px-4 whitespace-nowrap">
                                    <x-button type="button" class="btn-editar" id="btn-editar-empresa-contable-{{ $item->id }}"
                                        wire:click="$dispatch('empresa-contable-editar', {id: {{ $item->id }}})"
                                        title="{{ __('Modificar') }}">
                                        <i class="fa-solid fa-pen"> </i>
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

        {{-- El formulario de empresa contable se monta globalmente en layouts.app
             (también accesible desde el badge de la barra superior), no aquí. --}}
    </x-slot>
</x-botonera-page>

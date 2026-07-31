<x-botonera-page>
    <x-slot name="title">
        {{ __('Propietarios') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Propietarios de inmuebles') }}
    </x-slot>
    <x-slot name="botonera">
        <x-button type="button" class="btn btn-nuevo" id="btn-nuevo-propietario"
            wire:click="$dispatch('abrir-crear-propietario')" title="{{ __('Nuevo') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
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
                                    <x-button type="button" class="btn-editar" id="btn-editar-propietario-{{ $item->id }}"
                                        wire:click="$dispatch('propietario-editar', {id: {{ $item->id }}})"
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

        @livewire('propietarios.formulario')
    </x-slot>
</x-botonera-page>

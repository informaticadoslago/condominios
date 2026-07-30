<x-botonera-page>
    <x-slot name="title">
        {{ __('Países') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Países seleccionables y su grupo (España / UE / Resto)') }}
    </x-slot>
    <x-slot name="botonera">
        <x-button type="button" class="btn btn-nuevo" id="btn-nuevo-pais"
            wire:click="$dispatch('abrir-crear-pais')" title="{{ __('Nuevo') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
        </x-button>
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        @php
            $grupoLabels = [
                \App\Models\Pais::GRUPO_UE   => __('UE'),
                \App\Models\Pais::GRUPO_OTRO => __('Resto'),
            ];
        @endphp
        <x-dosl.tabla>
            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => "Nombre o código"])
            </div>
            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="cursor-pointer py-3 px-6" wire:click="ordenar('nombre')">
                                {{ __('Nombre') }}
                                @if ($sort == 'nombre')
                                    <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                @else
                                    <i class="fa-solid fa-sort float-right mt-1"></i>
                                @endif
                            </th>
                            <th class="py-3 px-6">{{ __('Código') }}</th>
                            <th class="py-3 px-6">{{ __('Grupo') }}</th>
                            <th class="py-3 px-6">{{ __('Estado') }}</th>
                            <th class="py-3 px-6">{{ __('Accion') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                <td class="px-6 py-4 mayusculas">{{ $item->nombre }}</td>
                                <td class="px-6 py-4">{{ $item->codigo1 }}</td>
                                <td class="px-6 py-4">{{ $grupoLabels[$item->grupo] ?? $item->grupo }}</td>
                                <td class="px-6 py-4">{{ $item->descripcionEstado }}</td>
                                <td class="px-4 whitespace-nowrap">
                                    @if ($item->estado_id == \App\Models\Pais::ESTADO_ACTIVO)
                                        <x-button type="button" class="btn-editar" id="btn-editar-pais-{{ $item->id }}"
                                            wire:click="$dispatch('pais-editar', {id: {{ $item->id }}})"
                                            title="{{ __('Modificar') }}">
                                            <i class="fa-solid fa-pen"> </i>
                                        </x-button>
                                        <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white ml-1"
                                            wire:click="confirmarBaja({{ $item->id }})"
                                            title="{{ __('Desactivar') }}">
                                            <i class="fa-solid fa-trash"> </i>
                                        </x-button>
                                    @else
                                        <x-button type="button" class="bg-green-600 hover:bg-green-700 text-white"
                                            wire:click="confirmarReactivar({{ $item->id }})"
                                            title="{{ __('Activar') }}">
                                            <i class="fa-solid fa-arrow-rotate-left"> </i>
                                        </x-button>
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

        @livewire('maestros.paises.formulario')
    </x-slot>
</x-botonera-page>

<x-botonera-page>
    <x-slot name="title">
        {{ __('Ejercicios contables') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Años/periodos contables') }}
    </x-slot>
    <x-slot name="botonera">
        <x-button type="button" class="btn btn-nuevo" id="btn-nuevo-ejercicio-contable"
            wire:click="$dispatch('abrir-crear-ejercicio-contable')" title="{{ __('Nuevo') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
        </x-button>
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => "Nombre"])
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
                            <th class="cursor-pointer py-3 px-6" wire:click="ordenar('fecha_inicio')">
                                {{ __('Fecha inicio') }}
                                @if ($sort == 'fecha_inicio')
                                    <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                @else
                                    <i class="fa-solid fa-sort float-right mt-1"></i>
                                @endif
                            </th>
                            <th class="cursor-pointer py-3 px-6" wire:click="ordenar('fecha_fin')">
                                {{ __('Fecha fin') }}
                                @if ($sort == 'fecha_fin')
                                    <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                @else
                                    <i class="fa-solid fa-sort float-right mt-1"></i>
                                @endif
                            </th>
                            <th class="py-3 px-6">{{ __('Cerrado') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                <td class="px-6 py-4">
                                    <span class="mayusculas">{{ $item->nombre }}</span>
                                </td>
                                <td class="px-6 py-4">{{ $item->fecha_inicio?->format('d/m/Y') }}</td>
                                <td class="px-6 py-4">{{ $item->fecha_fin?->format('d/m/Y') }}</td>
                                <td class="px-6 py-4">{{ $item->cerrado ? __('Sí') : __('No') }}</td>
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

        @livewire('ejercicios-contables.formulario')
    </x-slot>
</x-botonera-page>

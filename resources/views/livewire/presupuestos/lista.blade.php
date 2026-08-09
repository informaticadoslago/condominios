<x-botonera-page>
    <x-slot name="title">
        {{ __('Presupuestos') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Presupuestos de la comunidad') }}
    </x-slot>
    <x-slot name="botonera">
        <x-button type="button" class="btn btn-nuevo" id="btn-nuevo-presupuesto"
            wire:click="$dispatch('abrir-crear-presupuesto')" title="{{ __('Nuevo') }}">
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
                            <th class="cursor-pointer py-3 px-6" wire:click="ordenar('anho')">
                                {{ __('Año') }}
                                @if ($sort == 'anho')
                                    <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                @else
                                    <i class="fa-solid fa-sort float-right mt-1"></i>
                                @endif
                            </th>
                            <th class="py-3 px-6">{{ __('Estado') }}</th>
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                <td class="px-6 py-4">
                                    <span class="mayusculas">{{ $item->nombre }}</span>
                                </td>
                                <td class="px-6 py-4">{{ $item->anho }}</td>
                                <td class="px-6 py-4">{{ $item->estado?->descripcion }}</td>
                                <td class="px-4 whitespace-nowrap">
                                    <a href="{{ route('presupuestos.conceptos', $item) }}" wire:navigate class="btn-editar"
                                        id="btn-conceptos-presupuesto-{{ $item->id }}" title="{{ __('Conceptos') }}">
                                        <i class="fa-solid fa-list"> </i>
                                    </a>
                                    <a href="{{ route('presupuestos.reparto', $item) }}" wire:navigate class="btn-editar ml-1"
                                        id="btn-reparto-presupuesto-{{ $item->id }}" title="{{ __('Reparto') }}">
                                        <i class="fa-solid fa-table-cells"> </i>
                                    </a>
                                    <a href="{{ route('presupuestos.conceptos.pdf', $item) }}" target="_blank" class="btn-editar ml-1"
                                        id="btn-imprimir-presupuesto-{{ $item->id }}"
                                        title="{{ __('Imprimir presupuesto') }}">
                                        <i class="fa-solid fa-print"> </i>
                                    </a>
                                    <x-button type="button" class="btn-editar ml-1" id="btn-editar-presupuesto-{{ $item->id }}"
                                        wire:click="$dispatch('presupuesto-editar', {id: {{ $item->id }}})"
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

        @livewire('presupuestos.formulario')
    </x-slot>
</x-botonera-page>

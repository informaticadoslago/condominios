<x-botonera-page>
    <x-slot name="title">
        {{ __('Exámenes') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Calendario de exámenes del horario') }}
    </x-slot>
    <x-slot name="botonera">
        <x-button type="button" class="btn btn-nuevo" id="btn-nuevo-examen"
            wire:click="$dispatch('abrir-crear-examen')" title="{{ __('Nuevo') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
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
            </div>
            @include('livewire.parciales.filtros')

            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            @foreach ($this->columnas as $clave)
                                @switch($clave)
                                    @case('fecha')
                                        <th class="cursor-pointer py-3 px-6" wire:click="ordenar('fecha')">
                                            {{ __('Fecha') }}
                                            @if ($sort == 'fecha')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
                                        @break
                                    @case('dia')
                                        <th class="py-3 px-6">{{ __('Día') }}</th>
                                        @break
                                    @case('hora')
                                        <th class="cursor-pointer py-3 px-6" wire:click="ordenar('hora')">
                                            {{ __('Hora') }}
                                            @if ($sort == 'hora')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
                                        @break
                                    @case('asignatura')
                                        <th class="py-3 px-6">{{ __('Asignatura') }}</th>
                                        @break
                                @endswitch
                            @endforeach
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            {{-- El que ya pasó se ve en gris: sigue en la lista como histórico. --}}
                            <tr wire:key="{{ $item->id }}" @class(['text-gray-400' => $item->fecha->lt(today())])>
                                @foreach ($this->columnas as $clave)
                                    @switch($clave)
                                        @case('fecha')
                                            <td class="px-6 py-4">{{ $item->fecha->format('d/m/Y') }}</td>
                                            @break
                                        @case('dia')
                                            <td class="px-6 py-4">{{ \App\Support\DiaSemana::deFecha($item->fecha)->nombre() }}</td>
                                            @break
                                        @case('hora')
                                            <td class="px-6 py-4">{{ substr($item->hora, 0, 5) }}</td>
                                            @break
                                        @case('asignatura')
                                            <td class="px-6 py-4">{{ $item->asignatura?->nombre }}</td>
                                            @break
                                    @endswitch
                                @endforeach
                                <td class="px-4 whitespace-nowrap">
                                    <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white"
                                        id="btn-eliminar-examen-{{ $item->id }}"
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

        @livewire('examenes.formulario')
    </x-slot>
</x-botonera-page>

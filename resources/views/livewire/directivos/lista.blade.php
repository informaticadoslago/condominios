<x-botonera-page>
    <x-slot name="title">
        {{ __('Directiva') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Miembros de la directiva de la comunidad') }}
    </x-slot>
    <x-slot name="botonera">
        <x-button type="button" class="btn btn-nuevo" id="btn-nuevo-directivo"
            wire:click="$dispatch('abrir-crear-directivo')" title="{{ __('Nuevo') }}">
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
                                    @case('puesto')
                                        <th class="cursor-pointer py-3 px-6" wire:click="ordenar('tipo_directivo_comunidad_id')">
                                            {{ __('Puesto') }}
                                            @if ($sort == 'tipo_directivo_comunidad_id')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
                                        @break
                                    @case('nombre')
                                        <th class="py-3 px-6">{{ __('Nombre') }}</th>
                                        @break
                                    @case('documento')
                                        <th class="py-3 px-6">{{ __('Documento') }}</th>
                                        @break
                                    @case('fecha_inicio')
                                        <th class="cursor-pointer py-3 px-6" wire:click="ordenar('fecha_inicio')">
                                            {{ __('Fecha de inicio') }}
                                            @if ($sort == 'fecha_inicio')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
                                        @break
                                    @case('fecha_fin')
                                        <th class="cursor-pointer py-3 px-6" wire:click="ordenar('fecha_fin')">
                                            {{ __('Fecha de fin') }}
                                            @if ($sort == 'fecha_fin')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
                                        @break
                                @endswitch
                            @endforeach
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            {{-- El que ya cesó se ve en gris: sigue en la lista como histórico. --}}
                            <tr wire:key="{{ $item->id }}" @class(['text-gray-400' => $item->fecha_fin?->isPast()])>
                                @foreach ($this->columnas as $clave)
                                    @switch($clave)
                                        @case('puesto')
                                            <td class="px-6 py-4">{{ $item->tipoDirectivo?->descripcion }}</td>
                                            @break
                                        @case('nombre')
                                            <td class="px-6 py-4">{{ $item->persona?->nombre_completo }}</td>
                                            @break
                                        @case('documento')
                                            <td class="px-6 py-4">{{ $item->persona?->documento_identificativo }}</td>
                                            @break
                                        @case('fecha_inicio')
                                            <td class="px-6 py-4">{{ $item->fecha_inicio?->format('d/m/Y') }}</td>
                                            @break
                                        @case('fecha_fin')
                                            <td class="px-6 py-4">{{ $item->fecha_fin?->format('d/m/Y') ?? '—' }}</td>
                                            @break
                                    @endswitch
                                @endforeach
                                <td class="px-4 whitespace-nowrap">
                                    <x-button type="button" class="btn-editar" id="btn-editar-directivo-{{ $item->id }}"
                                        wire:click="$dispatch('directivo-editar', {id: {{ $item->id }}})"
                                        title="{{ __('Modificar') }}">
                                        <i class="fa-solid fa-pen"> </i>
                                    </x-button>
                                    <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white ml-1"
                                        id="btn-eliminar-directivo-{{ $item->id }}"
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

        @livewire('directivos.formulario')
    </x-slot>
</x-botonera-page>

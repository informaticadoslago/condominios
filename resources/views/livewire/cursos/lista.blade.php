<x-botonera-page>
    <x-slot name="title">
        {{ __('Cursos') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Cursos del horario y sus evaluaciones') }}
    </x-slot>
    <x-slot name="botonera">
        <x-button type="button" class="btn btn-nuevo" id="btn-nuevo-curso"
            wire:click="$dispatch('abrir-crear-curso')" title="{{ __('Nuevo') }}">
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
                @include('livewire.parciales.buscador', ['placeholder' => "Nombre"])
            </div>
            @include('livewire.parciales.filtros')
            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            @foreach ($this->columnas as $clave)
                                @switch($clave)
                                    @case('nombre')
                                        <th class="cursor-pointer py-3 px-6" wire:click="ordenar('nombre')">
                                            {{ __('Nombre') }}
                                            @if ($sort == 'nombre')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
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
                                    @case('evaluaciones')
                                        <th class="py-3 px-6 text-right">{{ __('Evaluaciones') }}</th>
                                        @break
                                    @case('estado')
                                        <th class="py-3 px-6">{{ __('Estado') }}</th>
                                        @break
                                @endswitch
                            @endforeach
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                @foreach ($this->columnas as $clave)
                                    @switch($clave)
                                        @case('nombre')
                                            <td class="px-6 py-4">{{ $item->nombre }}</td>
                                            @break
                                        @case('fecha_inicio')
                                            <td class="px-6 py-4">{{ $item->fecha_inicio->format('d/m/Y') }}</td>
                                            @break
                                        @case('fecha_fin')
                                            <td class="px-6 py-4">{{ $item->fecha_fin->format('d/m/Y') }}</td>
                                            @break
                                        @case('evaluaciones')
                                            <td class="px-6 py-4 text-right">{{ $item->evaluaciones_count }}</td>
                                            @break
                                        @case('estado')
                                            <td class="px-6 py-4">
                                                <span class="mayusculas">{{ $item->estado?->descripcion }}</span>
                                                @if ($item->historial_estados_count > 1)
                                                    <button type="button" wire:click="verHistorial({{ $item->id }})"
                                                        class="ml-2 text-gray-500 hover:text-gray-800" title="{{ __('Historial de estados') }}">
                                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                                    </button>
                                                @endif
                                            </td>
                                            @break
                                    @endswitch
                                @endforeach
                                <td class="px-4 whitespace-nowrap">
                                    <a href="{{ route('cursos.evaluaciones', $item->id) }}" wire:navigate
                                        id="btn-evaluaciones-curso-{{ $item->id }}" title="{{ __('Evaluaciones') }}"
                                        class="inline-flex items-center px-4 py-2 mr-1 bg-indigo-600 hover:bg-indigo-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150">
                                        <i class="fa-solid fa-list-check"> </i>
                                    </a>
                                    <x-button type="button" class="btn-editar" id="btn-editar-curso-{{ $item->id }}"
                                        wire:click="$dispatch('curso-editar', {id: {{ $item->id }}})"
                                        title="{{ __('Modificar') }}">
                                        <i class="fa-solid fa-pen"> </i>
                                    </x-button>
                                    @if ($item->estado_id == \App\Models\Curso::ESTADO_ACTIVO)
                                        {{-- Clic normal: baja por estado. Mayús+clic: borrado físico (irreversible),
                                             que solo sale adelante si ningún otro registro lo usa. --}}
                                        <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white ml-1"
                                            x-on:click="$event.shiftKey ? $wire.confirmarEliminarFisico({{ $item->id }}) : $wire.confirmarBaja({{ $item->id }})"
                                            title="{{ __('Dar de baja (Mayús+clic: borrar definitivamente)') }}">
                                            <i class="fa-solid fa-ban"> </i>
                                        </x-button>
                                    @else
                                        <x-button type="button" class="bg-green-600 hover:bg-green-700 text-white ml-1"
                                            x-on:click="$event.shiftKey ? $wire.confirmarEliminarFisico({{ $item->id }}) : $wire.confirmarReactivar({{ $item->id }})"
                                            title="{{ __('Reactivar (Mayús+clic: borrar definitivamente)') }}">
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

        @livewire('cursos.formulario')
        @include('livewire.parciales.modal-historial-estado')
    </x-slot>
</x-botonera-page>

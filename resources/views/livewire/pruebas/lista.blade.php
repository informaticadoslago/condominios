<x-botonera-page>
    <x-slot name="title">
        {{ __('Pruebas evaluables') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ $asignatura->nombreConModulo() }}
    </x-slot>
    <x-slot name="botonera">
        {{-- La cabecera apila en vertical lo que recibe: los dos botones van en una fila propia.
             Sin items-*: una fila flex estira a sus hijos, así que miden lo mismo de alto. --}}
        <div class="flex flex-row gap-2">
            {{-- Mismas clases de caja que x-button, para que mida lo mismo que «Nuevo». --}}
            <a href="{{ route('asignaturas.index') }}" wire:navigate id="btn-volver-asignaturas"
                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs uppercase tracking-widest btn btn-cerrar"
                title="{{ __('Volver a asignaturas') }}">
                <i class="fa-solid fa-arrow-left mr-1"></i>{{ __('Asignaturas') }}
            </a>
            <x-button type="button" class="btn btn-nuevo" id="btn-nuevo-prueba"
                wire:click="$dispatch('abrir-crear-prueba', {evaluacionId: {{ (int) ($filtros['evaluacion_id'] ?? 0) }}})" title="{{ __('Nuevo') }}">
                <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
            </x-button>
        </div>
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
            {{-- Con una evaluación elegida: lo que suman los pesos de sus pruebas activas. No tiene
                 que llegar a 100 (pueden faltar pruebas por crear); si pasa, en rojo. --}}
            @if ($sumaPesos !== null)
                <div class="py-2 px-6 text-sm border-b" id="suma-pesos">
                    {{ __('Suma de pesos de la evaluación') }}:
                    <span @class(['font-semibold', 'text-red-600' => $sumaPesos > 100])>{{ \App\Models\Prueba::numero($sumaPesos) }} %</span>
                </div>
            @endif
            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            {{-- Orden manual: clic para volver a él; con él en pantalla, las filas se arrastran. --}}
                            <th class="cursor-pointer py-3 pl-6 w-8" wire:click="ordenar('orden')"
                                title="{{ $arrastrar ? __('Arrastra las filas para cambiar el orden de impresión') : __('Ver en el orden de impresión, para poder arrastrar') }}">
                                <i class="fa-solid fa-grip-lines {{ $arrastrar ? '' : 'text-gray-300' }}"></i>
                            </th>
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
                                    @case('evaluacion')
                                        <th class="py-3 px-6">{{ __('Evaluación') }}</th>
                                        @break
                                    @case('puntuacion_maxima')
                                        <th class="cursor-pointer py-3 px-6 text-right" wire:click="ordenar('puntuacion_maxima')">
                                            {{ __('Nota sobre') }}
                                            @if ($sort == 'puntuacion_maxima')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
                                        @break
                                    @case('peso')
                                        <th class="cursor-pointer py-3 px-6 text-right" wire:click="ordenar('peso')">
                                            {{ __('Peso') }}
                                            @if ($sort == 'peso')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
                                        @break
                                    @case('notas')
                                        <th class="py-3 px-6 text-right">{{ __('Notas') }}</th>
                                        @break
                                    @case('estado')
                                        <th class="py-3 px-6">{{ __('Estado') }}</th>
                                        @break
                                @endswitch
                            @endforeach
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    {{-- sobre: la fila sobre la que está el arrastre, para marcarla. Al soltar, la
                         arrastrada queda después de ella si iba hacia abajo y antes si iba hacia arriba. --}}
                    <tbody class="divide-y" x-data="{ sobre: null }">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}"
                                @if ($arrastrar)
                                    draggable="true"
                                    x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $item->id }}')"
                                    x-on:dragend="sobre = null"
                                    x-on:dragover.prevent="sobre = {{ $item->id }}"
                                    x-on:dragleave="if (sobre === {{ $item->id }}) sobre = null"
                                    x-on:drop.prevent="sobre = null; $wire.mover($event.dataTransfer.getData('text/plain'), {{ $item->id }})"
                                    :style="sobre === {{ $item->id }} ? 'outline: 2px solid #3b82f6; outline-offset: -2px' : ''"
                                @endif>
                                <td class="pl-6 py-4 {{ $arrastrar ? 'cursor-move text-gray-400' : 'text-gray-300' }}">
                                    <i class="fa-solid fa-grip-lines"></i>
                                </td>
                                @foreach ($this->columnas as $clave)
                                    @switch($clave)
                                        @case('nombre')
                                            <td class="px-6 py-4">{{ $item->nombre }}</td>
                                            @break
                                        @case('fecha')
                                            <td class="px-6 py-4">{{ $item->fecha->format('d/m/Y') }}</td>
                                            @break
                                        @case('evaluacion')
                                            <td class="px-6 py-4">{{ $item->evaluacion->nombre.' ('.$item->evaluacion->curso->nombre.')' }}</td>
                                            @break
                                        @case('puntuacion_maxima')
                                            <td class="px-6 py-4 text-right">{{ \App\Models\Prueba::numero($item->puntuacion_maxima) }}</td>
                                            @break
                                        @case('peso')
                                            <td class="px-6 py-4 text-right">{{ \App\Models\Prueba::numero($item->peso).' %' }}</td>
                                            @break
                                        @case('notas')
                                            <td class="px-6 py-4 text-right">{{ $item->notas_count }}</td>
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
                                    <a href="{{ route('asignaturas.pruebas.notas', [$asignaturaId, $item->id]) }}" wire:navigate
                                        id="btn-notas-prueba-{{ $item->id }}" title="{{ __('Notas') }}"
                                        class="inline-flex items-center px-4 py-2 mr-1 bg-indigo-600 hover:bg-indigo-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest transition ease-in-out duration-150">
                                        <i class="fa-solid fa-pen-to-square"> </i>
                                    </a>
                                    <x-button type="button" class="btn-editar" id="btn-editar-prueba-{{ $item->id }}"
                                        wire:click="$dispatch('prueba-editar', {id: {{ $item->id }}})"
                                        title="{{ __('Modificar') }}">
                                        <i class="fa-solid fa-pen"> </i>
                                    </x-button>
                                    @if ($item->estado_id == \App\Models\Prueba::ESTADO_ACTIVO)
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

        @livewire('pruebas.formulario', ['asignaturaId' => $asignaturaId])
        @include('livewire.parciales.modal-historial-estado')
    </x-slot>
</x-botonera-page>

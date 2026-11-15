<x-botonera-page>
    <x-slot name="title">
        {{ __('Alumnos') }}
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
            <x-button type="button" class="btn btn-nuevo" id="btn-nuevo-alumno"
                wire:click="$dispatch('abrir-crear-alumno')" title="{{ __('Nuevo') }}">
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
                {{-- La revisión de lo importado es una tabla de cinco columnas: con el móvil en
                     vertical no cabe, así que ahí no se ofrece. --}}
                <x-secondary-button type="button" class="max-md:portrait:hidden" id="btn-importar-alumnos"
                    wire:click="$dispatch('abrir-importar-alumnos')" title="{{ __('Importar alumnos de un listado') }}">
                    <i class="fa-solid fa-file-import mr-1"></i>{{ __('Importar') }}
                </x-secondary-button>
            </x-slot>

            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => "Nombre, apellidos o grupo"])
            </div>
            @include('livewire.parciales.filtros')
            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            @foreach ($this->columnas as $clave)
                                @switch($clave)
                                    @case('apellido1')
                                        <th class="cursor-pointer py-3 px-6" wire:click="ordenar('apellido1')">
                                            {{ __('Primer apellido') }}
                                            @if ($sort == 'apellido1')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
                                        @break
                                    @case('apellido2')
                                        <th class="cursor-pointer py-3 px-6" wire:click="ordenar('apellido2')">
                                            {{ __('Segundo apellido') }}
                                            @if ($sort == 'apellido2')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
                                        @break
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
                                    @case('fecha_nacimiento')
                                        <th class="cursor-pointer py-3 px-6" wire:click="ordenar('fecha_nacimiento')">
                                            {{ __('Fecha de nacimiento') }}
                                            @if ($sort == 'fecha_nacimiento')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
                                        @break
                                    @case('grupo')
                                        <th class="cursor-pointer py-3 px-6" wire:click="ordenar('grupo')">
                                            {{ __('Grupo') }}
                                            @if ($sort == 'grupo')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
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
                                        @case('apellido1')
                                            <td class="px-6 py-4">{{ $item->alumno->apellido1 }}</td>
                                            @break
                                        @case('apellido2')
                                            <td class="px-6 py-4">{{ $item->alumno->apellido2 }}</td>
                                            @break
                                        @case('nombre')
                                            <td class="px-6 py-4">{{ $item->alumno->nombre }}</td>
                                            @break
                                        @case('fecha_nacimiento')
                                            <td class="px-6 py-4">{{ $item->alumno->fecha_nacimiento->format('d/m/Y') }}</td>
                                            @break
                                        @case('grupo')
                                            <td class="px-6 py-4">{{ $item->grupo }}</td>
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
                                    <x-button type="button" class="btn-editar" id="btn-editar-alumno-{{ $item->id }}"
                                        wire:click="$dispatch('alumno-editar', {id: {{ $item->id }}})"
                                        title="{{ __('Modificar') }}">
                                        <i class="fa-solid fa-pen"> </i>
                                    </x-button>
                                    @if ($item->estado_id == \App\Models\AsignaturaAlumno::ESTADO_ACTIVO)
                                        <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white ml-1"
                                            wire:click="confirmarBaja({{ $item->id }})" title="{{ __('Dar de baja') }}">
                                            <i class="fa-solid fa-ban"> </i>
                                        </x-button>
                                    @else
                                        {{-- Clic normal: reactivar. Mayús+clic: borrado físico (irreversible). --}}
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

        @livewire('alumnos.formulario', ['asignaturaId' => $asignaturaId])
        @livewire('alumnos.importar', ['asignaturaId' => $asignaturaId])
        @include('livewire.parciales.modal-historial-estado')
    </x-slot>
</x-botonera-page>

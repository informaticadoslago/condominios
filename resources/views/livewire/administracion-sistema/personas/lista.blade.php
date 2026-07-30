<x-botonera-page>
    <x-slot name="title">
        {{ __('Personas') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Personas del sistema') }}
    </x-slot>
    <x-slot name="botonera">
        {{-- Sin botón Nuevo: una persona sola no es nada, se crea al darle su primer rol
             (nuevo profesor, nuevo usuario...). Los formularios crear/editar de persona
             se conservan en el código pero no se montan. --}}
    </x-slot>

    <x-slot name="content">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 ">
            <x-dosl.tabla>
                <div class="py-3 px-6 flex items-center">
                    @include('livewire.parciales.lineas_x_pagina')
                    @include('livewire.parciales.buscador', ['placeholder' => "Algo que buscar"])
                </div>
                @if (count($personas))
                    <table class="table-striped w-full table-auto text-sm text-left">
                        <thead class="font-medium border-b">
                            <tr>
                                <th class="w24 cursor-pointer py-3 px-6" wire:click="ordenar('nombre')">
                                    {{ __('Nombre') }}
                                    @if ($sort == 'nombre')
                                        @if ($direction == 'asc')
                                            <i class="fa-solid fa-sort-up float-right mt-1"></i>
                                        @else
                                            <i class="fa-solid fa-sort-down float-right mt-1"></i>
                                        @endif
                                    @else
                                        <i class="fa-solid fa-sort float-right mt-1"></i>
                                    @endif
                                </th>
                                <th class="py-3 px-6">{{ __('Roles') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">

                            @foreach ($personas as $persona)
                                <tr wire:key="{{ $persona->id }}">
                                    <td class="px-6 py-4">
                                        <span class="mayusculas">{{ $persona->nombre_completo }}</span>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        @if ($persona->usuario)
                                            <button type="button" class="btn btn-editar text-xs px-2 py-1 mr-1"
                                                wire:click="$dispatch('usuarioeditar', {usuario_id: {{ $persona->usuario->id }}})"
                                                title="{{ __('Modificar usuario') }}">
                                                <i class="fa-solid fa-user-gear"></i> {{ __('Usuario') }}
                                            </button>
                                        @endif
                                        @if ($persona->socio)
                                            {{-- T: hacer botón cuando exista el edit de socios --}}
                                            <span class="btn text-xs px-2 py-1 mr-1 opacity-60 cursor-default"
                                                title="{{ __('Edición de socios pendiente') }}">
                                                <i class="fa-solid fa-id-card"></i> {{ __('Socio') }}
                                            </span>
                                        @endif
                                        @if ($persona->alumno)
                                            {{-- T: hacer botón cuando exista el edit de alumnos --}}
                                            <span class="btn text-xs px-2 py-1 mr-1 opacity-60 cursor-default"
                                                title="{{ __('Edición de alumnos pendiente') }}">
                                                <i class="fa-solid fa-graduation-cap"></i> {{ __('Alumno') }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($personas->hasPages())
                        <div class="px-6 py-3">
                            {{ $personas->links() }}
                        </div>
                    @endif
                @else
                    <div class="py-3 px-6">{{ __('No se encontraron resultados.') }}</div>
                @endif

            </x-dosl.tabla>
        </div>
        {{-- Formularios de persona conservados pero fuera de uso (ver nota en botonera):
        @livewire('administracion-sistema.personas.crear')
        @livewire('administracion-sistema.personas.editar') --}}

        {{-- Edición de roles desde la lista de personas --}}
        @livewire('administracion-sistema.usuarios.editar')
    </x-slot>
</x-botonera-page>

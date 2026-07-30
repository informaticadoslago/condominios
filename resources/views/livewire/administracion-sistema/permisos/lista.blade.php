<x-botonera-page>
            <x-slot name="title">
            {{ __('Permisos') }}
        </x-slot>
        <x-slot name="subtitulo">
            {{ __('Permisos de acceso') }}
        </x-slot>
        <x-slot name="botonera">
            @can('permiso-create')
                <x-ui-button type="button" class="btn btn-nuevo" id="btn-nuevo-permiso" wire:click="$dispatch('abrir-crear')"
                    title="{{ __('Nuevo') }}">
                    <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
                </x-ui-button>
            @endcan
        </x-slot>
    
        <x-slot name="content">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 ">
                <x-dosl.tabla>
                    <div class="py-3 px-6 flex items-center">
                        @include('livewire.parciales.lineas_x_pagina')
                        @include('livewire.parciales.buscador', ['placeholder' => "Algo que buscar"])
                    </div>
                    @if (count($permisos))
                        <table class="table-striped w-full table-auto text-sm text-left">
                            <thead class="font-medium border-b">
                                <tr>
                                    <th class="w24 cursor-pointer py-3 px-6"  wire:click="ordenar('name')">{{ __('Nombre') }}
                                        @if ($sort == 'name')
                                            @if ($direction == 'asc')
                                                <i class="fa-solid fa-sort-up float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort-down float-right mt-1"></i>
                                            @endif
                                        @else
                                            <i class="fa-solid fa-sort float-right mt-1"></i>
                                        @endif
                                    </th>
                                    <th class="py-3 px-6">{{ __('Accion') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                
                                @foreach ($permisos as $permiso)
                                    <tr wire:key="{{ $permiso->id }}">
                                        <td class="px-6 py-4">
                                            <span class="mayusculas">{{ $permiso->name }}</span>
                                        </td>
                                        <td class="px-4 whitespace-nowrap">
                                            @can('permiso-edit')
                                                <x-ui-button type="button" class="btn-editar" id="btn-nuevo-permiso"
                                                    @click="$dispatch('permisoeditar',{permiso_id: {{ $permiso->id }}})"
                                                    title="{{ __('Modificar') }}">
                                                    <i class="fa-solid fa-pen"> </i>
                                                </x-ui-button>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if ($permisos->hasPages())
                            <div class="px-6 py-3">
                                {{ $permisos->links() }}
                            </div>
                        @endif
                    @else
                        <div class="py-3 px-6">{{ __('No se encontraron resultados.') }}</div>
                    @endif
    
                </x-dosl.tabla>
            </div>
            @livewire('administracion-sistema.permisos.crear')
            @livewire('administracion-sistema.permisos.editar')            
        </x-slot>
    </x-botonera-page>
<x-botonera-page>
    <x-slot name="title">
        {{ __('Proveedores') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Proveedores de la comunidad') }}
    </x-slot>
    <x-slot name="botonera">
        <x-button type="button" class="btn btn-nuevo" id="btn-nuevo-proveedor"
            wire:click="$dispatch('abrir-crear-proveedor')" title="{{ __('Nuevo') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
        </x-button>
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            <x-slot name="botonera">
                <x-secondary-button type="button" id="btn-analizar-factura"
                    wire:click="$dispatch('abrir-analizar-factura')" title="{{ __('Analizar factura') }}">
                    <i class="fa-solid fa-file-invoice mr-1"></i>{{ __('Analizar factura') }}
                </x-secondary-button>
                @include('livewire.parciales.selector-columnas')
                <x-secondary-button type="button" wire:click="borrarFiltro" title="{{ __('Borrar filtro') }}">
                    <i class="fa-solid fa-filter-circle-xmark mr-1"></i>{{ __('Borrar filtro') }}
                </x-secondary-button>
            </x-slot>

            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => "Nombre o documento"])
            </div>
            @include('livewire.parciales.filtros')
            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            @foreach ($this->columnas as $clave)
                                @switch($clave)
                                    @case('nombre')
                                        <th class="py-3 px-6">{{ __('Nombre') }}</th>
                                        @break
                                    @case('documento')
                                        <th class="py-3 px-6">{{ __('Documento') }}</th>
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
                                            <td class="px-6 py-4">
                                                <span class="mayusculas">{{ $item->persona->nombreCompleto ?? '' }}</span>
                                            </td>
                                            @break
                                        @case('documento')
                                            <td class="px-6 py-4">{{ $item->persona->documento_identificativo ?? '' }}</td>
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
                                    <x-button type="button" class="btn" id="btn-ver-proveedor-{{ $item->id }}"
                                        wire:click="$dispatch('proveedor-ver', {id: {{ $item->id }}})"
                                        title="{{ __('Ver') }}">
                                        <i class="fa-solid fa-eye"> </i>
                                    </x-button>
                                    <x-button type="button" class="btn-editar ml-1" id="btn-editar-proveedor-{{ $item->id }}"
                                        wire:click="$dispatch('proveedor-editar', {id: {{ $item->id }}})"
                                        title="{{ __('Modificar') }}">
                                        <i class="fa-solid fa-pen"> </i>
                                    </x-button>
                                    @if ($item->estado_id == \App\Models\Proveedor::ESTADO_ACTIVO)
                                        <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white ml-1"
                                            wire:click="confirmarBaja({{ $item->id }})" title="{{ __('Dar de baja') }}">
                                            <i class="fa-solid fa-ban"> </i>
                                        </x-button>
                                    @else
                                        <x-button type="button" class="bg-green-600 hover:bg-green-700 text-white ml-1"
                                            x-on:click="$event.shiftKey ? $wire.confirmarBorrarDefinitivo({{ $item->id }}) : $wire.confirmarReactivar({{ $item->id }})"
                                            title="{{ __('Reactivar (mayús+clic: borrar definitivamente proveedor, documentos y plantilla)') }}">
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

        @livewire('proveedores.ver')
        @livewire('proveedores.formulario')
        @livewire('proveedores.analizar-factura')
        @livewire('proveedores.resultado-factura')
        @livewire('proveedores.marcar-plantilla-factura')
        @include('livewire.parciales.modal-historial-estado')
    </x-slot>
</x-botonera-page>

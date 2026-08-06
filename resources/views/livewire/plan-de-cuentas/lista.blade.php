<x-botonera-page>
    <x-slot name="title">
        {{ __('Cuentas contables') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Plan de cuentas de la empresa') }}
    </x-slot>
    <x-slot name="botonera">
        <x-button type="button" class="btn btn-nuevo" id="btn-nuevo-cuenta-contable"
            wire:click="$dispatch('abrir-crear-cuenta-contable')" title="{{ __('Nuevo') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
        </x-button>
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => "Código o nombre"])
            </div>
            @include('livewire.parciales.filtros')
            @if (count($filas))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="cursor-pointer py-3 px-6" wire:click="ordenar('codigo')">
                                {{ __('Código') }}
                                @if ($sort == 'codigo')
                                    <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                @else
                                    <i class="fa-solid fa-sort float-right mt-1"></i>
                                @endif
                            </th>
                            <th class="cursor-pointer py-3 px-6" wire:click="ordenar('nombre')">
                                {{ __('Nombre') }}
                                @if ($sort == 'nombre')
                                    <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                @else
                                    <i class="fa-solid fa-sort float-right mt-1"></i>
                                @endif
                            </th>
                            <th class="py-3 px-6">{{ __('Tipo') }}</th>
                            <th class="py-3 px-6">{{ __('Estado') }}</th>
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($filas as $item)
                            <tr wire:key="{{ $item->id }}">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @include('livewire.parciales.arbol-cuentas-codigo')
                                </td>
                                <td class="px-6 py-4">
                                    <span class="mayusculas">{{ $item->nombre }}</span>
                                </td>
                                <td class="px-6 py-4">{{ $item->tipoCuentaContable?->descripcion }}</td>
                                <td class="px-6 py-4">
                                    <span class="mayusculas">{{ $item->estado?->descripcion }}</span>
                                    @if ($item->historial_estados_count > 1)
                                        <button type="button" wire:click="verHistorial({{ $item->id }})"
                                            class="ml-2 text-gray-500 hover:text-gray-800" title="{{ __('Historial de estados') }}">
                                            <i class="fa-solid fa-clock-rotate-left"></i>
                                        </button>
                                    @endif
                                </td>
                                <td class="px-4 whitespace-nowrap">
                                    @if ($item->estado_id == \App\Models\CuentaContable::ESTADO_ACTIVO)
                                        <x-button type="button" class="btn-editar" id="btn-editar-cuenta-contable-{{ $item->id }}"
                                            wire:click="$dispatch('cuenta-contable-editar', {id: {{ $item->id }}})"
                                            title="{{ __('Modificar') }}">
                                            <i class="fa-solid fa-pen"> </i>
                                        </x-button>
                                        <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white ml-1"
                                            wire:click="confirmarBaja({{ $item->id }})"
                                            title="{{ __('Dar de baja') }}">
                                            <i class="fa-solid fa-trash"> </i>
                                        </x-button>
                                    @else
                                        <x-button type="button" class="bg-green-600 hover:bg-green-700 text-white ml-1"
                                            wire:click="confirmarReactivar({{ $item->id }})"
                                            title="{{ __('Reactivar') }}">
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

        @livewire('plan-de-cuentas.formulario')
        @include('livewire.parciales.modal-historial-estado')
    </x-slot>
</x-botonera-page>

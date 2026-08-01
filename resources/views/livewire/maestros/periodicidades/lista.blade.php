<x-botonera-page>
    <x-slot name="title">
        {{ __('Periodicidades de pago') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Periodicidades para el reparto de pagos de los presupuestos') }}
    </x-slot>
    <x-slot name="botonera">
        <x-button type="button" class="btn btn-nuevo" id="btn-nuevo-periodicidad"
            wire:click="$dispatch('abrir-crear-periodicidad')" title="{{ __('Nuevo') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
        </x-button>
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => "Descripción"])
            </div>
            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="cursor-pointer py-3 px-6" wire:click="ordenar('descripcion')">
                                {{ __('Descripción') }}
                                @if ($sort == 'descripcion')
                                    <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                @else
                                    <i class="fa-solid fa-sort float-right mt-1"></i>
                                @endif
                            </th>
                            <th class="cursor-pointer py-3 px-6" wire:click="ordenar('meses')">
                                {{ __('Meses') }}
                                @if ($sort == 'meses')
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
                                <td class="px-6 py-4">{{ $item->descripcion }}</td>
                                <td class="px-6 py-4">{{ $item->meses }}</td>
                                <td class="px-6 py-4">{{ $item->estado?->descripcion }}</td>
                                <td class="px-4 whitespace-nowrap">
                                    @if ($item->estado_id == \App\Models\TipoPeriodicidadPago::ESTADO_ACTIVO)
                                        <x-button type="button" class="btn-editar" id="btn-editar-periodicidad-{{ $item->id }}"
                                            wire:click="$dispatch('periodicidad-editar', {id: {{ $item->id }}})"
                                            title="{{ __('Modificar') }}">
                                            <i class="fa-solid fa-pen"> </i>
                                        </x-button>
                                        <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white ml-1"
                                            wire:click="confirmarBaja({{ $item->id }})"
                                            title="{{ __('Dar de baja') }}">
                                            <i class="fa-solid fa-trash"> </i>
                                        </x-button>
                                    @else
                                        <x-button type="button" class="bg-green-600 hover:bg-green-700 text-white"
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

        @livewire('maestros.periodicidades.formulario')
    </x-slot>
</x-botonera-page>

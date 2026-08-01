<x-botonera-page>
    <x-slot name="title">
        {{ __($titulo) }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __($subtitulo ?? '') }}
    </x-slot>
    <x-slot name="botonera">
        @unless ($bloqueado)
            <x-button type="button" class="btn btn-nuevo" id="btn-nuevo-catalogo"
                wire:click="$dispatch('abrir-crear-catalogo')" title="{{ __('Nuevo') }}">
                <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
            </x-button>
        @endunless
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
                            <th class="py-3 px-6">{{ __('Estado') }}</th>
                            @unless ($bloqueado)
                                <th class="py-3 px-6">{{ __('Acción') }}</th>
                            @endunless
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                <td class="px-6 py-4">{{ $item->descripcion }}</td>
                                <td class="px-6 py-4">{{ $item->estado?->descripcion }}</td>
                                @unless ($bloqueado)
                                    <td class="px-4 whitespace-nowrap">
                                        @if ($item->estado_id == \App\Models\Estado::ESTADO_ACTIVO)
                                            <x-button type="button" class="btn-editar" id="btn-editar-catalogo-{{ $item->id }}"
                                                wire:click="$dispatch('catalogo-editar', {id: {{ $item->id }}})"
                                                title="{{ __('Modificar') }}">
                                                <i class="fa-solid fa-pen"> </i>
                                            </x-button>
                                            {{-- Clic normal: baja por estado. Mayús+clic: borrado físico (irreversible). --}}
                                            <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white ml-1"
                                                x-on:click="$event.shiftKey ? $wire.confirmarEliminarFisico({{ $item->id }}) : $wire.confirmarBaja({{ $item->id }})"
                                                title="{{ __('Dar de baja (Mayús+clic: borrar definitivamente)') }}">
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
                                @endunless
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

        @unless ($bloqueado)
            @livewire('catalogos.formulario', ['clave' => $clave])
        @endunless
    </x-slot>
</x-botonera-page>

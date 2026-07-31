<x-botonera-page>
    <x-slot name="title">
        {{ __('Inmuebles') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Inmuebles de las comunidades') }}
    </x-slot>
    <x-slot name="botonera">
        <a href="{{ route('inmuebles.crear') }}" wire:navigate class="btn btn-nuevo" id="btn-nuevo-inmueble"
            title="{{ __('Nuevo') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
        </a>
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => "Puerta, referencia catastral o comunidad"])
            </div>
            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-3 px-6">{{ __('Comunidad') }}</th>
                            <th class="py-3 px-6">{{ __('Tipo') }}</th>
                            <th class="py-3 px-6">{{ __('Ocupación') }}</th>
                            <th class="py-3 px-6">{{ __('Planta') }}</th>
                            <th class="py-3 px-6">{{ __('Puerta') }}</th>
                            <th class="py-3 px-6">{{ __('Coeficiente') }}</th>
                            <th class="py-3 px-6">{{ __('Propietarios') }}</th>
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                <td class="px-6 py-4">{{ $item->comunidad->nombre }}</td>
                                <td class="px-6 py-4">{{ $item->tipoInmueble->descripcion }}</td>
                                <td class="px-6 py-4">{{ $item->ocupacion->descripcion }}</td>
                                <td class="px-6 py-4">{{ $item->planta }}</td>
                                <td class="px-6 py-4">{{ $item->puerta }}</td>
                                <td class="px-6 py-4">{{ $item->coeficiente }}%</td>
                                <td class="px-6 py-4">
                                    {{ $item->propietarios->map(fn ($p) => $p->persona->nombreCompleto)->join(', ') }}
                                </td>
                                <td class="px-4 whitespace-nowrap">
                                    <a href="{{ route('inmuebles.editar', $item) }}" wire:navigate class="btn-editar"
                                        id="btn-editar-inmueble-{{ $item->id }}" title="{{ __('Modificar') }}">
                                        <i class="fa-solid fa-pen"> </i>
                                    </a>
                                    <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white ml-1"
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
    </x-slot>
</x-botonera-page>

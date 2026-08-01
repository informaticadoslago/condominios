<x-botonera-page>
    <x-slot name="title">
        {{ __('Propietarios') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Propietarios de inmuebles') }}
    </x-slot>
    <x-slot name="botonera">
        <a href="{{ route('propietarios.crear') }}" wire:navigate class="btn btn-nuevo" id="btn-nuevo-propietario" title="{{ __('Nuevo') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
        </a>
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        @if ($borradores->count())
            <x-dosl.tabla>
                <div class="py-3 px-6 flex items-center gap-2 text-red-700 dark:text-red-400">
                    <i class="fa-solid fa-bell"></i>
                    <span class="font-semibold">{{ __('Propietarios sin terminar') }}</span>
                </div>
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-3 px-6">{{ __('Persona') }}</th>
                            <th class="py-3 px-6">{{ __('Modificado') }}</th>
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($borradores as $borrador)
                            <tr wire:key="borrador-{{ $borrador->id }}">
                                <td class="px-6 py-4">{{ $borrador->nombreBorrador ?: __('(sin datos todavía)') }}</td>
                                <td class="px-6 py-4">{{ $borrador->updated_at->format('d-m-Y H:i') }}</td>
                                <td class="px-4 whitespace-nowrap">
                                    <a href="{{ route('propietarios.crear', ['borrador' => $borrador->id]) }}" wire:navigate
                                        class="btn-editar" id="btn-retomar-borrador-{{ $borrador->id }}" title="{{ __('Retomar') }}">
                                        <i class="fa-solid fa-pen"> </i> {{ __('Retomar') }}
                                    </a>
                                    <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white ml-1"
                                        wire:click="confirmarDescartarBorrador({{ $borrador->id }})" title="{{ __('Descartar') }}">
                                        <i class="fa-solid fa-trash"> </i>
                                    </x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-dosl.tabla>
        @endif

        <x-dosl.tabla>
            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => "Nombre o documento"])
            </div>
            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-3 px-6">{{ __('Nombre') }}</th>
                            <th class="py-3 px-6">{{ __('Documento') }}</th>
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                <td class="px-6 py-4">
                                    <span class="mayusculas">{{ $item->persona->nombreCompleto ?? '' }}</span>
                                </td>
                                <td class="px-6 py-4">{{ $item->persona->documento_identificativo ?? '' }}</td>
                                <td class="px-4 whitespace-nowrap">
                                    <a href="{{ route('propietarios.editar', $item) }}" wire:navigate class="btn-editar"
                                        id="btn-editar-propietario-{{ $item->id }}" title="{{ __('Modificar') }}">
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

<x-botonera-page>
    <x-slot name="title">
        {{ __('Remesas') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Envíos de adeudos al banco') }}
    </x-slot>
    <x-slot name="botonera">
        <x-button type="button" class="btn btn-nuevo" wire:click="abrirNueva" title="{{ __('Nueva') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nueva') }}
        </x-button>
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            <x-slot name="botonera">
                <x-secondary-button type="button" wire:click="borrarFiltro" title="{{ __('Borrar filtro') }}">
                    <i class="fa-solid fa-filter-circle-xmark mr-1"></i>{{ __('Borrar filtro') }}
                </x-secondary-button>
                @include('livewire.parciales.selector-columnas')
            </x-slot>

            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => 'Referencia'])
            </div>
            @include('livewire.parciales.filtros')

            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            @if ($this->verColumna('referencia'))
                                <th class="cursor-pointer py-3 px-6" wire:click="ordenar('referencia')">
                                    {{ __('Referencia') }}
                                    @if ($sort == 'referencia')
                                        <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                    @else
                                        <i class="fa-solid fa-sort float-right mt-1"></i>
                                    @endif
                                </th>
                            @endif
                            @if ($this->verColumna('fecha_cargo'))
                                <th class="cursor-pointer py-3 px-6" wire:click="ordenar('fecha_cargo')">
                                    {{ __('Fecha de cargo') }}
                                    @if ($sort == 'fecha_cargo')
                                        <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                    @else
                                        <i class="fa-solid fa-sort float-right mt-1"></i>
                                    @endif
                                </th>
                            @endif
                            @if ($this->verColumna('cuenta'))
                                <th class="py-3 px-6">{{ __('Cuenta de abono') }}</th>
                            @endif
                            @if ($this->verColumna('recibos'))
                                <th class="py-3 px-6 text-right">{{ __('Recibos') }}</th>
                            @endif
                            @if ($this->verColumna('importe'))
                                <th class="py-3 px-6 text-right">{{ __('Importe') }}</th>
                            @endif
                            @if ($this->verColumna('devueltos'))
                                <th class="py-3 px-6 text-right">{{ __('Devueltos') }}</th>
                            @endif
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                @if ($this->verColumna('referencia'))
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $item->referencia }}</td>
                                @endif
                                @if ($this->verColumna('fecha_cargo'))
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $item->fecha_cargo?->format('d/m/Y') }}</td>
                                @endif
                                @if ($this->verColumna('cuenta'))
                                    <td class="px-6 py-4">{{ $item->cuentaBancaria?->iban }}</td>
                                @endif
                                @if ($this->verColumna('recibos'))
                                    <td class="px-6 py-4 text-right">{{ $item->lineas_count }}</td>
                                @endif
                                @if ($this->verColumna('importe'))
                                    <td class="px-6 py-4 text-right">
                                        {{ number_format((float) $item->lineas_sum_importe, 2, ',', '.') }}
                                    </td>
                                @endif
                                @if ($this->verColumna('devueltos'))
                                    <td class="px-6 py-4 text-right">
                                        @if ($item->lineas_devueltas_count)
                                            <span class="text-red-700 dark:text-red-400">{{ $item->lineas_devueltas_count }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                @endif
                                <td class="px-4 whitespace-nowrap">
                                    {{-- Enlace normal y no wire:navigate: es una descarga, no una página. --}}
                                    <a href="{{ route('remesas.fichero', $item) }}" class="btn-editar"
                                        title="{{ __('Descargar el fichero para el banco') }}">
                                        <i class="fa-solid fa-download"> </i>
                                    </a>
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

        {{-- Nueva remesa: se elige qué vencimiento se cobra y cuándo lo carga el banco.
             Los recibos los reúne GeneradorRemesa; aquí no se eligen uno a uno. --}}
        <x-dosl.dialog-modal wire:model.live="nuevaAbierta" maxWidth="lg">
            <x-slot name="title">
                {{ __('Nueva remesa') }}
            </x-slot>

            <x-slot name="content">
                <div class="mb-4">
                    <x-label for="nuevaVencimiento">{{ __('Vencimiento que se cobra') }}:</x-label>
                    <x-input class="block mt-1 w-full" type="date" id="nuevaVencimiento" wire:model="nuevaVencimiento" />
                    <x-input-error for="nuevaVencimiento" class="mt-1" />
                    <p class="mt-1 text-sm text-gray-500">
                        {{ __('Entran los recibos domiciliados de esa fecha que sigan pendientes y no estén ya presentados.') }}
                    </p>
                </div>

                <div>
                    <x-label for="nuevaFechaCargo">{{ __('Fecha de cargo') }}:</x-label>
                    <x-input class="block mt-1 w-full" type="date" id="nuevaFechaCargo" wire:model="nuevaFechaCargo" />
                    <x-input-error for="nuevaFechaCargo" class="mt-1" />
                    <p class="mt-1 text-sm text-gray-500">
                        {{ __('El día en que el banco carga los adeudos en la cuenta de cada propietario.') }}
                    </p>
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button type="button" wire:click="$set('nuevaAbierta', false)">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-button type="button" class="ml-2" wire:click="generar">
                    {{ __('Generar remesa') }}
                </x-button>
            </x-slot>
        </x-dosl.dialog-modal>
    </x-slot>
</x-botonera-page>

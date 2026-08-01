<x-botonera-page>
    <x-slot name="title">
        {{ __('Asientos contables') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Diario de asientos') }}
    </x-slot>
    <x-slot name="botonera">
        @if ((int) ($filtros['ejercicio_contable_id'] ?? 0) > 0)
            <a href="{{ route('asientos-contables.crear', ['ejercicioContable' => $filtros['ejercicio_contable_id']]) }}"
                wire:navigate class="btn btn-nuevo" id="btn-nuevo-asiento-contable" title="{{ __('Nuevo') }}">
                <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
            </a>
        @else
            <x-button type="button" class="btn btn-nuevo" id="btn-nuevo-asiento-contable" disabled title="{{ __('Nuevo') }}">
                <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
            </x-button>
        @endif
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        @if (session('mensaje'))
            <div class="mb-4 rounded-lg bg-green-100 border border-green-300 px-4 py-2 text-sm text-green-800 dark:bg-green-900/40 dark:border-green-700 dark:text-green-200">
                {{ session('mensaje') }}
            </div>
        @endif
        <x-dosl.tabla>
            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => "Concepto"])
            </div>
            @include('livewire.parciales.filtros')
            @if ((int) ($filtros['ejercicio_contable_id'] ?? 0) === 0)
                <div class="py-2 px-6 text-sm text-gray-500">
                    {{ __('Elige un ejercicio en el filtro para poder crear un asiento nuevo.') }}
                </div>
            @endif
            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="cursor-pointer py-3 px-6" wire:click="ordenar('numero')">
                                {{ __('Número') }}
                                @if ($sort == 'numero')
                                    <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                @else
                                    <i class="fa-solid fa-sort float-right mt-1"></i>
                                @endif
                            </th>
                            <th class="cursor-pointer py-3 px-6" wire:click="ordenar('fecha')">
                                {{ __('Fecha') }}
                                @if ($sort == 'fecha')
                                    <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                @else
                                    <i class="fa-solid fa-sort float-right mt-1"></i>
                                @endif
                            </th>
                            <th class="py-3 px-6">{{ __('Ejercicio') }}</th>
                            <th class="cursor-pointer py-3 px-6" wire:click="ordenar('concepto')">
                                {{ __('Concepto') }}
                                @if ($sort == 'concepto')
                                    <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                @else
                                    <i class="fa-solid fa-sort float-right mt-1"></i>
                                @endif
                            </th>
                            <th class="py-3 px-6 text-right">{{ __('Importe') }}</th>
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                <td class="px-6 py-4">{{ $item->numero }}</td>
                                <td class="px-6 py-4">{{ $item->fecha?->format('d/m/Y') }}</td>
                                <td class="px-6 py-4">{{ $item->ejercicioContable?->nombre }}</td>
                                <td class="px-6 py-4">{{ $item->concepto }}</td>
                                <td class="px-6 py-4 text-right">{{ number_format($item->total_debe ?? 0, 2, ',', '.') }}</td>
                                <td class="px-4 whitespace-nowrap">
                                    <x-button type="button" class="btn-editar" id="btn-detalle-asiento-contable-{{ $item->id }}"
                                        wire:click="toggleDetalle({{ $item->id }})" title="{{ __('Ver líneas') }}">
                                        <i class="fa-solid fa-eye"> </i>
                                    </x-button>
                                </td>
                            </tr>
                            @if (in_array($item->id, $expandido, true))
                                <tr wire:key="{{ $item->id }}-detalle">
                                    <td colspan="6" class="px-6 py-4 bg-gray-50 dark:bg-gray-800">
                                        <table class="w-full text-sm text-left">
                                            <thead>
                                                <tr class="border-b">
                                                    <th class="py-1 pr-4">{{ __('Cuenta') }}</th>
                                                    <th class="py-1 pr-4">{{ __('Concepto') }}</th>
                                                    <th class="py-1 pr-4 text-right">{{ __('Debe') }}</th>
                                                    <th class="py-1 text-right">{{ __('Haber') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($item->apuntesContables as $apunte)
                                                    <tr wire:key="apunte-{{ $apunte->id }}">
                                                        <td class="py-1 pr-4">{{ $apunte->cuentaContable?->codigo }} - {{ $apunte->cuentaContable?->nombre }}</td>
                                                        <td class="py-1 pr-4">{{ $apunte->concepto }}</td>
                                                        <td class="py-1 pr-4 text-right">{{ $apunte->debe > 0 ? number_format($apunte->debe, 2, ',', '.') : '' }}</td>
                                                        <td class="py-1 text-right">{{ $apunte->haber > 0 ? number_format($apunte->haber, 2, ',', '.') : '' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            @endif
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

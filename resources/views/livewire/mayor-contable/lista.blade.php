<x-botonera-page>
    <x-slot name="title">
        {{ __('Libro mayor') }}
    </x-slot>
    <x-slot name="subtitulo">
        @if ($cuenta)
            {{ $cuenta->codigo }} - {{ $cuenta->nombre }}
        @else
            {{ __('Movimientos de una cuenta entre dos fechas') }}
        @endif
    </x-slot>
    <x-slot name="botonera">
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            <x-slot name="botonera">
                @include('livewire.parciales.selector-columnas')
                <x-secondary-button type="button" wire:click="borrarFiltro" title="{{ __('Borrar filtro') }}">
                    <i class="fa-solid fa-filter-circle-xmark mr-1"></i>{{ __('Borrar filtro') }}
                </x-secondary-button>
            </x-slot>

            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
            </div>
            @include('livewire.parciales.filtros')

            @if (! $cuenta)
                <div class="py-3 px-6 text-sm text-gray-500">
                    {{ __('Elige una cuenta para ver su mayor.') }}
                </div>
            @else
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            @if ($this->verColumna('fecha'))
                                <th class="py-3 px-6">{{ __('Fecha') }}</th>
                            @endif
                            @if ($this->verColumna('asiento'))
                                <th class="py-3 px-6">{{ __('Asiento') }}</th>
                            @endif
                            @if ($this->verColumna('concepto'))
                                <th class="py-3 px-6">{{ __('Concepto') }}</th>
                            @endif
                            @if ($this->verColumna('contrapartida'))
                                <th class="py-3 px-6">{{ __('Contrapartida') }}</th>
                            @endif
                            @if ($this->verColumna('debe'))
                                <th class="py-3 px-6 text-right">{{ __('Debe') }}</th>
                            @endif
                            @if ($this->verColumna('haber'))
                                <th class="py-3 px-6 text-right">{{ __('Haber') }}</th>
                            @endif
                            @if ($this->verColumna('saldo'))
                                <th class="py-3 px-6 text-right">{{ __('Saldo') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        {{-- El arrastre solo tiene sentido delante del primer apunte del rango. --}}
                        @if ($items->onFirstPage() && $this->verColumna('saldo'))
                            <tr class="font-medium">
                                @if (count($this->columnas) > 1)
                                    <td class="px-6 py-3" colspan="{{ count($this->columnas) - 1 }}">
                                        {{ __('Saldo anterior') }}
                                    </td>
                                @endif
                                <td class="px-6 py-3 text-right">{{ number_format($saldoInicial / 100, 2, ',', '.') }}</td>
                            </tr>
                        @endif

                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                @if ($this->verColumna('fecha'))
                                    <td class="px-6 py-4">{{ $item->asientoContable?->fecha?->format('d/m/Y') }}</td>
                                @endif
                                @if ($this->verColumna('asiento'))
                                    <td class="px-6 py-4">{{ $item->asientoContable?->numero }}</td>
                                @endif
                                @if ($this->verColumna('concepto'))
                                    <td class="px-6 py-4">{{ $item->concepto ?: $item->asientoContable?->concepto }}</td>
                                @endif
                                @if ($this->verColumna('contrapartida'))
                                    <td class="px-6 py-4">
                                        {{ $item->contrapartidas()->map(fn ($cuenta) => $cuenta->codigo.' - '.$cuenta->nombre)->implode(', ') }}
                                    </td>
                                @endif
                                @if ($this->verColumna('debe'))
                                    <td class="px-6 py-4 text-right">{{ $item->debe > 0 ? number_format($item->debe_euros, 2, ',', '.') : '' }}</td>
                                @endif
                                @if ($this->verColumna('haber'))
                                    <td class="px-6 py-4 text-right">{{ $item->haber > 0 ? number_format($item->haber_euros, 2, ',', '.') : '' }}</td>
                                @endif
                                @if ($this->verColumna('saldo'))
                                    {{-- acumulado viene de la window function, en céntimos y sin el arrastre. --}}
                                    <td class="px-6 py-4 text-right">{{ number_format(($saldoInicial + (int) $item->acumulado) / 100, 2, ',', '.') }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="font-medium border-t">
                        @php
                            // Las de importe van siempre al final: lo que quede delante es
                            // lo que abarca la etiqueta de los totales.
                            $columnasImporte = count(array_intersect($this->columnas, ['debe', 'haber', 'saldo']));
                        @endphp
                        <tr>
                            @if (count($this->columnas) > $columnasImporte)
                                <td class="px-6 py-3" colspan="{{ count($this->columnas) - $columnasImporte }}">
                                    {{ __('Totales del periodo') }}
                                </td>
                            @endif
                            @if ($this->verColumna('debe'))
                                <td class="px-6 py-3 text-right">{{ number_format($sumas['debe'] / 100, 2, ',', '.') }}</td>
                            @endif
                            @if ($this->verColumna('haber'))
                                <td class="px-6 py-3 text-right">{{ number_format($sumas['haber'] / 100, 2, ',', '.') }}</td>
                            @endif
                            @if ($this->verColumna('saldo'))
                                <td class="px-6 py-3 text-right">{{ number_format($saldoFinal / 100, 2, ',', '.') }}</td>
                            @endif
                        </tr>
                    </tfoot>
                </table>
                @if (! count($items))
                    <div class="py-3 px-6">{{ __('La cuenta no tiene movimientos en esas fechas.') }}</div>
                @endif
                @if ($items->hasPages())
                    <div class="px-6 py-3">
                        {{ $items->links() }}
                    </div>
                @endif
            @endif
        </x-dosl.tabla>
    </x-slot>
</x-botonera-page>

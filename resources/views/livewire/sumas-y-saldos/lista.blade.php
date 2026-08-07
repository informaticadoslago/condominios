<x-botonera-page>
    <x-slot name="title">
        {{ __('Sumas y saldos') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Saldo de cada cuenta entre dos fechas') }}
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

            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            @foreach (['codigo' => __('Código'), 'nombre' => __('Cuenta')] as $clave => $etiqueta)
                                @if ($this->verColumna($clave))
                                    <th class="cursor-pointer py-3 px-6" wire:click="ordenar('{{ $clave }}')">
                                        {{ $etiqueta }}
                                        @if ($sort == $clave)
                                            <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                        @else
                                            <i class="fa-solid fa-sort float-right mt-1"></i>
                                        @endif
                                    </th>
                                @endif
                            @endforeach
                            @foreach (['saldo_inicial' => __('Saldo anterior'), 'debe' => __('Debe'), 'haber' => __('Haber'), 'saldo_final' => __('Saldo')] as $clave => $etiqueta)
                                @if ($this->verColumna($clave))
                                    <th class="cursor-pointer py-3 px-6 text-right" wire:click="ordenar('{{ $clave }}')">
                                        {{ $etiqueta }}
                                        @if ($sort == $clave)
                                            <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1 ml-1"></i>
                                        @else
                                            <i class="fa-solid fa-sort float-right mt-1 ml-1"></i>
                                        @endif
                                    </th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                @if ($this->verColumna('codigo'))
                                    <td class="px-6 py-4">{{ $item->codigo }}</td>
                                @endif
                                @if ($this->verColumna('nombre'))
                                    <td class="px-6 py-4">{{ $item->nombre }}</td>
                                @endif
                                @if ($this->verColumna('saldo_inicial'))
                                    <td class="px-6 py-4 text-right">{{ number_format($item->saldo_inicial / 100, 2, ',', '.') }}</td>
                                @endif
                                @if ($this->verColumna('debe'))
                                    <td class="px-6 py-4 text-right">{{ number_format($item->debe / 100, 2, ',', '.') }}</td>
                                @endif
                                @if ($this->verColumna('haber'))
                                    <td class="px-6 py-4 text-right">{{ number_format($item->haber / 100, 2, ',', '.') }}</td>
                                @endif
                                @if ($this->verColumna('saldo_final'))
                                    <td class="px-6 py-4 text-right">{{ number_format($item->saldo_final / 100, 2, ',', '.') }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="font-medium border-t">
                        @php
                            // Los totales son de TODO el balance, no solo de esta página.
                            $columnasImporte = count(array_intersect($this->columnas, ['saldo_inicial', 'debe', 'haber', 'saldo_final']));
                        @endphp
                        <tr>
                            @if (count($this->columnas) > $columnasImporte)
                                <td class="px-6 py-3" colspan="{{ count($this->columnas) - $columnasImporte }}">
                                    {{ __('Totales') }}
                                </td>
                            @endif
                            @if ($this->verColumna('saldo_inicial'))
                                <td class="px-6 py-3 text-right">{{ number_format($totales['saldo_inicial'] / 100, 2, ',', '.') }}</td>
                            @endif
                            @if ($this->verColumna('debe'))
                                <td class="px-6 py-3 text-right">{{ number_format($totales['debe'] / 100, 2, ',', '.') }}</td>
                            @endif
                            @if ($this->verColumna('haber'))
                                <td class="px-6 py-3 text-right">{{ number_format($totales['haber'] / 100, 2, ',', '.') }}</td>
                            @endif
                            @if ($this->verColumna('saldo_final'))
                                {{-- Cero, o hay un asiento descuadrado en la base de datos. --}}
                                <td class="px-6 py-3 text-right">{{ number_format($totales['saldo_final'] / 100, 2, ',', '.') }}</td>
                            @endif
                        </tr>
                    </tfoot>
                </table>
                @if ($items->hasPages())
                    <div class="px-6 py-3">
                        {{ $items->links() }}
                    </div>
                @endif
            @else
                <div class="py-3 px-6">{{ __('No hay movimientos en esas fechas.') }}</div>
            @endif
        </x-dosl.tabla>
    </x-slot>
</x-botonera-page>

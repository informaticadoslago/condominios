@php
    $euros = fn ($centimos) => $centimos === 0 ? '-' : number_format($centimos / 100, 2, ',', '.');
@endphp

<x-botonera-page>
    <x-slot name="title">
        {{ __('Movimientos') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Ingresos y gastos mes a mes, con la justificación del saldo') }}
    </x-slot>
    <x-slot name="botonera">
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            <x-slot name="botonera">
                <x-secondary-button type="button" wire:click="borrarFiltro" title="{{ __('Borrar filtro') }}">
                    <i class="fa-solid fa-filter-circle-xmark mr-1"></i>{{ __('Borrar filtro') }}
                </x-secondary-button>
            </x-slot>

            @include('livewire.parciales.filtros')

            @if (! $rango)
                <div class="py-3 px-6 text-sm text-gray-500">
                    {{ __('Elige las dos fechas del informe.') }}
                </div>
            @else
                {{-- Con muchos meses la tabla no cabe: que ruede ella, no la página. --}}
                <div class="px-6 py-4 overflow-x-auto">
                    @foreach ([
                        ['titulo' => __('Ingresos'), 'bloque' => $ingresos, 'total' => __('Total de ingresos')],
                        ['titulo' => __('Gastos'), 'bloque' => $gastos, 'total' => __('Total de gastos')],
                    ] as $seccion)
                        <table class="w-full table-auto text-sm text-left mb-8">
                            <thead class="font-medium border-b">
                                <tr>
                                    <th class="py-2 pr-4">{{ $seccion['titulo'] }}</th>
                                    <th class="py-2 px-3 text-right">{{ __('Total') }}</th>
                                    @foreach ($meses as $etiqueta)
                                        <th class="py-2 px-3 text-right whitespace-nowrap">{{ $etiqueta }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @forelse ($seccion['bloque']['filas'] as $fila)
                                    <tr>
                                        <td class="py-2 pr-4 whitespace-nowrap">{{ $fila['codigo'] }} - {{ $fila['nombre'] }}</td>
                                        <td class="py-2 px-3 text-right">{{ $euros($fila['total']) }}</td>
                                        @foreach ($meses as $mes => $etiqueta)
                                            <td class="py-2 px-3 text-right">{{ $euros($fila['meses'][$mes]) }}</td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="py-2 pr-4 text-gray-500" colspan="{{ count($meses) + 2 }}">
                                            {{ __('No hay movimientos en estas fechas.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="font-medium border-t">
                                <tr>
                                    <td class="py-2 pr-4">{{ $seccion['total'] }}</td>
                                    <td class="py-2 px-3 text-right">{{ $euros($seccion['bloque']['total']) }}</td>
                                    @foreach ($meses as $mes => $etiqueta)
                                        <td class="py-2 px-3 text-right">{{ $euros($seccion['bloque']['totales'][$mes]) }}</td>
                                    @endforeach
                                </tr>
                            </tfoot>
                        </table>
                    @endforeach
                </div>

                <div class="px-6 pb-6 grid gap-8 md:grid-cols-2">
                    <div>
                        <h3 class="font-semibold mb-2">{{ __('Resumen') }}</h3>
                        <table class="w-full table-auto text-sm">
                            <tbody class="divide-y">
                                <tr>
                                    <td class="py-2 pr-4">{{ __('Saldo anterior') }}</td>
                                    <td class="py-2 text-right">{{ number_format($saldoAnterior / 100, 2, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td class="py-2 pr-4">{{ __('Total de ingresos') }}</td>
                                    <td class="py-2 text-right">{{ number_format($ingresos['total'] / 100, 2, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td class="py-2 pr-4">{{ __('Total de gastos') }}</td>
                                    <td class="py-2 text-right">{{ number_format($gastos['total'] / 100, 2, ',', '.') }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="font-medium border-t">
                                <tr>
                                    <td class="py-2 pr-4">{{ __('Saldo anterior + ingresos - gastos') }}</td>
                                    <td class="py-2 text-right">{{ number_format($saldoFinal / 100, 2, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div>
                        <h3 class="font-semibold mb-2">{{ __('Justificación del saldo') }}</h3>
                        <table class="w-full table-auto text-sm">
                            <tbody class="divide-y">
                                @foreach ($justificacion as $cuenta)
                                    <tr wire:key="justificacion-{{ $cuenta->id }}">
                                        <td class="py-2 pr-4">{{ $cuenta->codigo }} - {{ $cuenta->nombre }}</td>
                                        <td class="py-2 text-right">{{ number_format($cuenta->saldo / 100, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="font-medium border-t">
                                <tr>
                                    <td class="py-2 pr-4">{{ __('Total') }}</td>
                                    <td class="py-2 text-right">{{ number_format($saldoFinal / 100, 2, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endif
        </x-dosl.tabla>
    </x-slot>
</x-botonera-page>

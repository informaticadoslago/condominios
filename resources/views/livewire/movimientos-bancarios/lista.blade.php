<x-botonera-page>
    <x-slot name="title">
        {{ __('Movimientos bancarios') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Volcado en bruto del extracto del banco') }}
    </x-slot>
    <x-slot name="botonera">
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            <x-slot name="botonera">
                <x-secondary-button type="button" id="btn-importar-movimientos-bancarios"
                    wire:click="$dispatch('abrir-importar-movimientos-bancarios')" title="{{ __('Importar') }}">
                    <i class="fa-solid fa-file-csv mr-1"></i>{{ __('Importar') }}
                </x-secondary-button>
                <x-secondary-button type="button" id="btn-convertir-en-comision" wire:click="convertirEnComision"
                    class="{{ $seleccionado ? '' : 'opacity-50 pointer-events-none' }}"
                    title="{{ __('Convertir en comisión bancaria') }}">
                    <i class="fa-solid fa-money-check-dollar mr-1"></i>{{ __('Convertir en comisión') }}
                </x-secondary-button>
                @include('livewire.parciales.selector-columnas')
                <x-secondary-button type="button" wire:click="borrarFiltro" title="{{ __('Borrar filtro') }}">
                    <i class="fa-solid fa-filter-circle-xmark mr-1"></i>{{ __('Borrar filtro') }}
                </x-secondary-button>
            </x-slot>

            <div class="py-3 px-6 flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <x-label for="cuentaBancariaId" :value="__('Cuenta bancaria')" class="mb-0" />
                    <x-select id="cuentaBancariaId" class="py-2" wire:model.live="cuentaBancariaId">
                        @foreach ($cuentasBancarias as $cuenta)
                            <option value="{{ $cuenta->id }}">{{ $cuenta->alias ?: $cuenta->iban }}</option>
                        @endforeach
                    </x-select>
                </div>
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => 'Descripción o referencia'])
            </div>
            @include('livewire.parciales.filtros')

            @if (! $cuentaBancariaId)
                <div class="py-3 px-6">{{ __('Esta comunidad no tiene ninguna cuenta bancaria dada de alta.') }}</div>
            @elseif (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-3 px-6 w-8"></th>
                            @if ($this->verColumna('fecha_valor'))
                                <th class="cursor-pointer py-3 px-6" wire:click="ordenar('fecha_valor')">
                                    {{ __('F. Valor') }}
                                    @if ($sort == 'fecha_valor')
                                        <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                    @else
                                        <i class="fa-solid fa-sort float-right mt-1"></i>
                                    @endif
                                </th>
                            @endif
                            @if ($this->verColumna('fecha_contable'))
                                <th class="cursor-pointer py-3 px-6" wire:click="ordenar('fecha_contable')">
                                    {{ __('F. Contable') }}
                                    @if ($sort == 'fecha_contable')
                                        <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                    @else
                                        <i class="fa-solid fa-sort float-right mt-1"></i>
                                    @endif
                                </th>
                            @endif
                            @if ($this->verColumna('fecha_operacion'))
                                <th class="cursor-pointer py-3 px-6" wire:click="ordenar('fecha_operacion')">
                                    {{ __('F. Operación') }}
                                    @if ($sort == 'fecha_operacion')
                                        <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                    @else
                                        <i class="fa-solid fa-sort float-right mt-1"></i>
                                    @endif
                                </th>
                            @endif
                            @if ($this->verColumna('tipo'))
                                <th class="py-3 px-6">{{ __('Tipo') }}</th>
                            @endif
                            @if ($this->verColumna('descripcion'))
                                <th class="py-3 px-6">{{ __('Descripción') }}</th>
                            @endif
                            @if ($this->verColumna('referencia'))
                                <th class="py-3 px-6">{{ __('Referencia') }}</th>
                            @endif
                            @if ($this->verColumna('importe'))
                                <th class="py-3 px-6 text-right">{{ __('Importe') }}</th>
                            @endif
                            @if ($this->verColumna('saldo'))
                                <th class="py-3 px-6 text-right">{{ __('Saldo') }}</th>
                            @endif
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                <td class="px-6 py-4">
                                    <input type="radio" wire:model.live="seleccionado" value="{{ $item->id }}"
                                        id="seleccionado-{{ $item->id }}" />
                                </td>
                                @if ($this->verColumna('fecha_valor'))
                                    <td class="px-6 py-4">{{ $item->fecha_valor?->format('d/m/Y') }}</td>
                                @endif
                                @if ($this->verColumna('fecha_contable'))
                                    <td class="px-6 py-4">{{ $item->fecha_contable?->format('d/m/Y') }}</td>
                                @endif
                                @if ($this->verColumna('fecha_operacion'))
                                    <td class="px-6 py-4">{{ $item->fecha_operacion?->format('d/m/Y') }}</td>
                                @endif
                                @if ($this->verColumna('tipo'))
                                    <td class="px-6 py-4">{{ $item->tipo_operacion }}</td>
                                @endif
                                @if ($this->verColumna('descripcion'))
                                    <td class="px-6 py-4">{{ $item->descripcion }}</td>
                                @endif
                                @if ($this->verColumna('referencia'))
                                    <td class="px-6 py-4">{{ $item->referencia }}</td>
                                @endif
                                @if ($this->verColumna('importe'))
                                    <td class="px-6 py-4 text-right">{{ number_format($item->importe, 2, ',', '.') }}</td>
                                @endif
                                @if ($this->verColumna('saldo'))
                                    <td class="px-6 py-4 text-right">{{ $item->saldo !== null ? number_format($item->saldo, 2, ',', '.') : '—' }}</td>
                                @endif
                                <td class="px-4 whitespace-nowrap">
                                    <x-secondary-button type="button" class="px-3 py-2 text-red-600" id="btn-borrar-movimiento-bancario-{{ $item->id }}"
                                        title="{{ __('Borrar') }}" wire:click="borrar({{ $item->id }})">
                                        <i class="fa-solid fa-trash text-base"></i>
                                    </x-secondary-button>
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

        @livewire('movimientos-bancarios.importar-csv')
        @livewire('movimientos-bancarios.convertir-en-comision')
    </x-slot>
</x-botonera-page>

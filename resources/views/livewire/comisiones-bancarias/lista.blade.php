<x-botonera-page>
    <x-slot name="title">
        {{ __('Comisiones bancarias') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Comisiones de remesa y mantenimiento de cuenta') }}
    </x-slot>
    <x-slot name="botonera">
        <a href="{{ route('comisiones-bancarias.crear') }}" wire:navigate class="btn btn-nuevo"
            id="btn-nueva-comision-bancaria" title="{{ __('Nueva') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nueva') }}
        </a>
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        @if (session('mensaje'))
            <div class="mb-4 rounded-lg bg-green-100 border border-green-300 px-4 py-2 text-sm text-green-800 dark:bg-green-900/40 dark:border-green-700 dark:text-green-200">
                {{ session('mensaje') }}
            </div>
        @endif
        <x-dosl.tabla>
            <x-slot name="botonera">
                <x-secondary-button type="button" id="btn-importar-comisiones-bancarias"
                    wire:click="$dispatch('abrir-importar-csv')" title="{{ __('Importar') }}">
                    <i class="fa-solid fa-file-csv mr-1"></i>{{ __('Importar') }}
                </x-secondary-button>
                @include('livewire.parciales.selector-columnas')
                <x-secondary-button type="button" wire:click="borrarFiltro" title="{{ __('Borrar filtro') }}">
                    <i class="fa-solid fa-filter-circle-xmark mr-1"></i>{{ __('Borrar filtro') }}
                </x-secondary-button>
            </x-slot>

            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => 'Concepto o referencia'])
            </div>
            @include('livewire.parciales.filtros')

            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            @if ($this->verColumna('fecha'))
                                <th class="cursor-pointer py-3 px-6" wire:click="ordenar('fecha')">
                                    {{ __('Fecha') }}
                                    @if ($sort == 'fecha')
                                        <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                    @else
                                        <i class="fa-solid fa-sort float-right mt-1"></i>
                                    @endif
                                </th>
                            @endif
                            @if ($this->verColumna('tipo'))
                                <th class="py-3 px-6">{{ __('Tipo') }}</th>
                            @endif
                            @if ($this->verColumna('cuenta'))
                                <th class="py-3 px-6">{{ __('Cuenta bancaria') }}</th>
                            @endif
                            @if ($this->verColumna('remesa'))
                                <th class="py-3 px-6">{{ __('Remesa') }}</th>
                            @endif
                            @if ($this->verColumna('concepto'))
                                <th class="cursor-pointer py-3 px-6" wire:click="ordenar('concepto')">
                                    {{ __('Concepto') }}
                                    @if ($sort == 'concepto')
                                        <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                    @else
                                        <i class="fa-solid fa-sort float-right mt-1"></i>
                                    @endif
                                </th>
                            @endif
                            @if ($this->verColumna('referencia'))
                                <th class="py-3 px-6">{{ __('Referencia') }}</th>
                            @endif
                            @if ($this->verColumna('importe'))
                                <th class="py-3 px-6 text-right">{{ __('Importe') }}</th>
                            @endif
                            @if ($this->verColumna('contabilizada'))
                                <th class="py-3 px-6">{{ __('Contabilizada') }}</th>
                            @endif
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                @if ($this->verColumna('fecha'))
                                    <td class="px-6 py-4">{{ $item->fecha?->format('d/m/Y') }}</td>
                                @endif
                                @if ($this->verColumna('tipo'))
                                    <td class="px-6 py-4">{{ $item->tipoComisionBancaria?->descripcion }}</td>
                                @endif
                                @if ($this->verColumna('cuenta'))
                                    <td class="px-6 py-4">{{ $item->cuentaBancaria?->alias ?: $item->cuentaBancaria?->iban }}</td>
                                @endif
                                @if ($this->verColumna('remesa'))
                                    <td class="px-6 py-4">{{ $item->remesa?->referencia ?: '—' }}</td>
                                @endif
                                @if ($this->verColumna('concepto'))
                                    <td class="px-6 py-4">{{ $item->concepto }}</td>
                                @endif
                                @if ($this->verColumna('referencia'))
                                    <td class="px-6 py-4">{{ $item->referencia }}</td>
                                @endif
                                @if ($this->verColumna('importe'))
                                    <td class="px-6 py-4 text-right">{{ number_format($item->lineas_sum_importe ?? 0, 2, ',', '.') }}</td>
                                @endif
                                @if ($this->verColumna('contabilizada'))
                                    <td class="px-6 py-4">
                                        @if ($item->asiento_contable)
                                            <i class="fa-solid fa-check text-green-600" title="{{ __('Contabilizada') }}"></i>
                                        @else
                                            <i class="fa-solid fa-xmark text-red-600" title="{{ __('Sin contabilizar') }}"></i>
                                        @endif
                                    </td>
                                @endif
                                <td class="px-4 whitespace-nowrap">
                                    <x-button type="button" class="btn-editar" id="btn-detalle-comision-bancaria-{{ $item->id }}"
                                        wire:click="toggleDetalle({{ $item->id }})" title="{{ __('Ver líneas') }}">
                                        <i class="fa-solid fa-eye"> </i>
                                    </x-button>
                                    @unless ($item->asiento_contable)
                                        <x-secondary-button type="button" class="px-3 py-2" id="btn-contabilizar-comision-bancaria-{{ $item->id }}"
                                            title="{{ __('Contabilizar') }}" wire:click="contabilizar({{ $item->id }})">
                                            <i class="fa-solid fa-scale-balanced text-base"></i>
                                        </x-secondary-button>
                                    @endunless
                                    <x-secondary-button type="button" class="px-3 py-2 text-red-600" id="btn-deshacer-comision-bancaria-{{ $item->id }}"
                                        title="{{ __('Deshacer') }}" wire:click="deshacer({{ $item->id }})">
                                        <i class="fa-solid fa-rotate-left text-base"></i>
                                    </x-secondary-button>
                                </td>
                            </tr>
                            @if (in_array($item->id, $expandido, true))
                                <tr wire:key="{{ $item->id }}-detalle">
                                    <td colspan="{{ count($this->columnas) + 1 }}" class="px-6 py-4 bg-gray-50 dark:bg-gray-800">
                                        <table class="w-full text-sm text-left">
                                            <thead>
                                                <tr class="border-b">
                                                    <th class="py-1 pr-4">{{ __('Concepto de la línea') }}</th>
                                                    <th class="py-1 text-right">{{ __('Importe') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($item->lineas as $linea)
                                                    <tr>
                                                        <td class="py-1 pr-4">{{ $linea->concepto }}</td>
                                                        <td class="py-1 text-right">{{ number_format($linea->importe, 2, ',', '.') }}</td>
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

        @livewire('comisiones-bancarias.importar-csv')
    </x-slot>
</x-botonera-page>

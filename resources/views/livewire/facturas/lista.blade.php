<x-botonera-page>
    <x-slot name="title">
        {{ __('Facturas') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Facturas de proveedores de la comunidad') }}
    </x-slot>
    <x-slot name="botonera">
        <a href="{{ route('facturas.crear') }}" class="btn btn-nuevo inline-flex items-center"
            id="btn-nueva-factura" title="{{ __('Nuevo') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
        </a>
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            <x-slot name="botonera">
                <x-secondary-button type="button" id="btn-importar-facturas"
                    wire:click="$dispatch('abrir-importar-facturas')" title="{{ __('Importar') }}">
                    <i class="fa-solid fa-folder-open mr-1"></i>{{ __('Importar') }}
                </x-secondary-button>
                <x-secondary-button type="button" wire:click="invertirSeleccion"
                    title="{{ __('Invierte la selección dentro de lo que cumple el filtro actual') }}">
                    <i class="fa-solid fa-arrow-right-arrow-left mr-1"></i>{{ __('Invertir selección') }}
                </x-secondary-button>
                @if (count($seleccionados))
                    <x-secondary-button type="button" wire:click="limpiarSeleccion" class="ml-1"
                        title="{{ __('Quitar toda la selección') }}">
                        <i class="fa-solid fa-xmark mr-1"></i>{{ __('Quitar selección') }} ({{ count($seleccionados) }})
                    </x-secondary-button>
                    <x-secondary-button type="button" wire:click="toggleVerSoloSeleccionados"
                        @class(['ml-1' => true, '!bg-blue-600 dark:!bg-blue-800 !text-white hover:!bg-blue-700 dark:hover:!bg-blue-700' => $verSoloSeleccionados])
                        title="{{ __('Ver solo las filas seleccionadas') }}">
                        <i class="fa-solid fa-check-double mr-1"></i>{{ __('Ver solo seleccionados') }}
                    </x-secondary-button>
                @endif
                @include('livewire.parciales.selector-columnas')
                <x-secondary-button type="button" wire:click="borrarFiltro" class="ml-1" title="{{ __('Borrar filtro') }}">
                    <i class="fa-solid fa-filter-circle-xmark mr-1"></i>{{ __('Borrar filtro') }}
                </x-secondary-button>
                {{-- Acciones en lote: actúan sobre las marcadas si hay alguna y, si no,
                     sobre todo lo que cumple el filtro. --}}
                <span class="ml-1 inline-block align-middle">
                    <x-dropdown align="right" width="60">
                        <x-slot name="trigger">
                            <button type="button" title="{{ __('Acciones en lote') }}"
                                class="p-2 rounded-lg text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white hover:bg-zinc-800/5 dark:hover:bg-white/10">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            @if (contabilidad_activa())
                                <x-dropdown-link href="#" wire:click="contabilizarLote">
                                    <i class="fa-solid fa-scale-balanced mr-1"></i>{{ __('Contabilizar') }}
                                    @if (count($seleccionados))
                                        ({{ count($seleccionados) }})
                                    @endif
                                </x-dropdown-link>
                            @endif
                            <x-dropdown-link href="#" wire:click="abrirPagoLote">
                                <i class="fa-solid fa-money-bill-transfer mr-1"></i>{{ __('Pagar') }}
                                @if (count($seleccionados))
                                    ({{ count($seleccionados) }})
                                @endif
                            </x-dropdown-link>
                        </x-slot>
                    </x-dropdown>
                </span>
            </x-slot>

            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => "Proveedor o número de factura"])
            </div>
            @include('livewire.parciales.filtros')
            @if (count($items))
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-3 px-6 w-px">
                                <input type="checkbox" wire:model.live="marcarTodosVisibles"
                                    title="{{ __('Marcar/desmarcar toda la página') }}" />
                            </th>
                            @if ($this->verColumna('cif'))
                                <th class="py-3 px-6">{{ __('CIF proveedor') }}</th>
                            @endif
                            @if ($this->verColumna('razon_social'))
                                <th class="py-3 px-6">{{ __('Razón social') }}</th>
                            @endif
                            @if ($this->verColumna('fecha_factura'))
                                <th class="cursor-pointer py-3 px-6" wire:click="ordenar('fecha_factura')">
                                    {{ __('Fecha factura') }}
                                    @if ($sort == 'fecha_factura')
                                        <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                    @else
                                        <i class="fa-solid fa-sort float-right mt-1"></i>
                                    @endif
                                </th>
                            @endif
                            @if ($this->verColumna('numero_factura'))
                                <th class="py-3 px-6">{{ __('Número factura') }}</th>
                            @endif
                            @if ($this->verColumna('importe'))
                                <th class="cursor-pointer py-3 px-6" wire:click="ordenar('importe')">
                                    {{ __('Importe') }}
                                    @if ($sort == 'importe')
                                        <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                    @else
                                        <i class="fa-solid fa-sort float-right mt-1"></i>
                                    @endif
                                </th>
                            @endif
                            <th class="py-3 px-6 text-center">{{ __('Soporte') }}</th>
                            @if (count($actividades))
                                <th class="py-3 px-6">{{ __('Actividad') }}</th>
                            @endif
                            @if (contabilidad_activa())
                                <th class="py-3 px-6">{{ __('Contabilidad') }}</th>
                            @endif
                            <th class="py-3 px-6">{{ __('Pago') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                <td class="px-6 py-4">
                                    <input type="checkbox" wire:model.live="seleccionados" value="{{ $item->id }}" />
                                </td>
                                @if ($this->verColumna('cif'))
                                    <td class="px-6 py-4">{{ $item->proveedor->persona->documento_identificativo ?? '' }}</td>
                                @endif
                                @if ($this->verColumna('razon_social'))
                                    <td class="px-6 py-4">
                                        <span class="mayusculas">{{ $item->proveedor->persona->nombreCompleto ?? '' }}</span>
                                    </td>
                                @endif
                                @if ($this->verColumna('fecha_factura'))
                                    <td class="px-6 py-4">
                                        {{ $item->fecha_factura }}
                                        <button type="button" class="text-gray-400 hover:text-gray-800 ml-1" title="{{ __('Corregir fecha') }}"
                                            wire:click="corregirCampo({{ $item->id }}, {{ \App\Models\TipoCampoPlantillaFactura::FECHA }})">
                                            <i class="fa-solid fa-pen text-xs"></i>
                                        </button>
                                    </td>
                                @endif
                                @if ($this->verColumna('numero_factura'))
                                    <td class="px-6 py-4">
                                        {{ $item->numero_factura }}
                                        <button type="button" class="text-gray-400 hover:text-gray-800 ml-1" title="{{ __('Corregir número') }}"
                                            wire:click="corregirCampo({{ $item->id }}, {{ \App\Models\TipoCampoPlantillaFactura::NUMERO_FACTURA }})">
                                            <i class="fa-solid fa-pen text-xs"></i>
                                        </button>
                                    </td>
                                @endif
                                @if ($this->verColumna('importe'))
                                    <td class="px-6 py-4">
                                        @if ($item->importe !== null)
                                            {{ number_format($item->importe, 2, ',', '.') }} €
                                        @endif
                                        <button type="button" class="text-gray-400 hover:text-gray-800 ml-1" title="{{ __('Corregir importe') }}"
                                            wire:click="corregirCampo({{ $item->id }}, {{ \App\Models\TipoCampoPlantillaFactura::IMPORTE }})">
                                            <i class="fa-solid fa-pen text-xs"></i>
                                        </button>
                                    </td>
                                @endif
                                {{-- Con papel, el ojo lo abre en otra pestaña (documentos.ver lo
                                     sirve inline, así que se ve en el navegador sin descargarlo).
                                     Sin documento la factura se tecleó (o se leyó su QR) y el papel
                                     puede llegar después: el propio «Sin soporte» hace de clip, y
                                     el label abre el selector sin necesitar una línea de JavaScript. --}}
                                <td class="px-6 py-4 text-center">
                                    @if ($item->documento)
                                        <a href="{{ route('documentos.ver', $item->documento) }}" target="_blank"
                                            class="text-gray-500 hover:text-gray-800" title="{{ __('Ver') }}">
                                            <i class="fa-solid fa-eye text-lg"></i>
                                        </a>
                                    @else
                                        <label class="cursor-pointer inline-flex items-center rounded px-2 py-0.5 text-xs font-medium bg-amber-100 text-amber-800 hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-200 dark:hover:bg-amber-900/70"
                                            title="{{ __('Adjuntar el papel de esta factura') }}"
                                            wire:loading.class="opacity-50" wire:target="soporte.{{ $item->id }}">
                                            <i class="fa-solid fa-paperclip mr-1"></i>{{ __('Sin soporte') }}
                                            <input type="file" class="hidden" accept=".pdf,.jpg,.jpeg,.png"
                                                wire:model="soporte.{{ $item->id }}" />
                                        </label>
                                    @endif
                                </td>
                                @if (count($actividades))
                                    <td class="px-6 py-4">
                                        <x-select class="block w-full py-2 text-sm" wire:change="actualizarActividad({{ $item->id }}, $event.target.value)"
                                            {{ $item->asiento_contable ? 'disabled' : '' }}>
                                            <option value="" {{ $item->actividad_id === null ? 'selected' : '' }}>{{ __('Sin actividad') }}</option>
                                            @foreach ($actividades as $id => $nombre)
                                                <option value="{{ $id }}" {{ $item->actividad_id == $id ? 'selected' : '' }}>{{ $nombre }}</option>
                                            @endforeach
                                        </x-select>
                                    </td>
                                @endif
                                {{-- Contabilizar es explícito: el gasto entra cuando quien lleva la
                                     comunidad lo manda, no al teclear la factura. Una vez hecho,
                                     queda el número de asiento y ya no hay botón. --}}
                                @if (contabilidad_activa())
                                    <td class="px-6 py-4">
                                        <span class="flex items-center gap-2">
                                            @if ($item->asiento_contable)
                                                <span class="text-gray-500" title="{{ __('Cuenta de gasto') }}: {{ $item->cuenta_gasto }}">
                                                    {{ __('Asiento') }} {{ $item->asiento_contable }}
                                                </span>
                                            @endif
                                            {{-- La balanza sigue ahí mientras quede algo por asentar: la
                                                 propia factura, o pagos suyos que se quedaron sin asiento. --}}
                                            @if ($item->faltaPorContabilizar())
                                                <x-secondary-button type="button" class="px-3 py-2"
                                                    title="{{ $item->asiento_contable ? __('Contabilizar los pagos pendientes') : __('Contabilizar') }}"
                                                    wire:click="contabilizar({{ $item->id }})">
                                                    <i class="fa-solid fa-scale-balanced text-base"></i>
                                                </x-secondary-button>
                                            @endif
                                        </span>
                                    </td>
                                @endif
                                {{-- El pago admite parciales, así que hasta que no queda a
                                     cero se sigue viendo lo que falta y el botón. Pagada, se
                                     puede desplegar el histórico de sus pagos. --}}
                                <td class="px-6 py-4">
                                    @if ($item->pendiente() <= 0)
                                        <button type="button" class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200"
                                            wire:click="toggleDetalle({{ $item->id }})" title="{{ __('Ver pagos') }}">
                                            {{ __('Pagada') }}
                                            <i class="fa-solid fa-chevron-{{ in_array($item->id, $expandido, true) ? 'up' : 'down' }} ml-1"></i>
                                        </button>
                                    @else
                                        <x-secondary-button type="button" class="px-3 py-2"
                                            title="{{ __('Pagar') }} ({{ __('pendiente') }}: {{ number_format($item->pendiente(), 2, ',', '.') }} €)"
                                            wire:click="$dispatch('abrir-pagar-factura', { id: {{ $item->id }} })">
                                            <i class="fa-solid fa-money-bill-transfer text-base"></i>
                                        </x-secondary-button>
                                    @endif
                                </td>
                            </tr>
                            @if ($item->pendiente() <= 0 && in_array($item->id, $expandido, true))
                                <tr wire:key="{{ $item->id }}-pagos">
                                    <td colspan="{{ count($this->columnas) + 3 + (contabilidad_activa() ? 1 : 0) }}"
                                        class="px-6 py-4 bg-gray-50 dark:bg-gray-800">
                                        <table class="w-full text-sm text-left">
                                            <thead>
                                                <tr class="border-b">
                                                    <th class="py-1 pr-4">{{ __('Fecha') }}</th>
                                                    <th class="py-1 pr-4 text-right">{{ __('Importe') }}</th>
                                                    @if (contabilidad_activa())
                                                        <th class="py-1 text-right">{{ __('Asiento') }}</th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($item->pagos as $pago)
                                                    <tr wire:key="pago-{{ $pago->id }}">
                                                        <td class="py-1 pr-4">{{ $pago->fecha?->format('d/m/Y') }}</td>
                                                        <td class="py-1 pr-4 text-right">{{ number_format($pago->importe, 2, ',', '.') }} €</td>
                                                        @if (contabilidad_activa())
                                                            <td class="py-1 text-right">{{ $pago->asiento_contable ?? '—' }}</td>
                                                        @endif
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

        {{-- Pago en lote: paga el pendiente completo de cada factura marcada en la misma
             fecha. Las que no se puedan pagar todavía se saltan solas. --}}
        <x-dosl.dialog-modal wire:model.live="pagoLoteAbierto" maxWidth="lg">
            <x-slot name="title">
                {{ __('Pagar facturas') }}
            </x-slot>

            <x-slot name="content">
                <div class="mb-4">
                    <x-label>{{ __('Facturas') }}:</x-label>
                    <p class="font-medium">{{ count($pagoLoteIds) }}</p>
                </div>

                <div>
                    <x-label for="pagoLoteFecha">{{ __('Fecha de pago') }}:</x-label>
                    <x-input class="block mt-1 w-full" type="date" id="pagoLoteFecha" wire:model="pagoLoteFecha" />
                    <x-input-error for="pagoLoteFecha" class="mt-1" />
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button type="button" wire:click="$set('pagoLoteAbierto', false)">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-button type="button" class="ml-2" wire:click="pagarLote">
                    {{ __('Sí, pagar') }}
                </x-button>
            </x-slot>
        </x-dosl.dialog-modal>

        @livewire('facturas.pagar-factura')
        @livewire('facturas.importar-facturas')
        @livewire('proveedores.resultado-factura')
        @livewire('proveedores.marcar-plantilla-factura')
    </x-slot>
</x-botonera-page>

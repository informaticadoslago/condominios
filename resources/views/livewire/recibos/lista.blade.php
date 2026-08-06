<x-botonera-page>
    <x-slot name="title">
        {{ __('Recibos') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Recibos de la comunidad') }}
    </x-slot>
    <x-slot name="botonera">
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            <x-slot name="botonera">
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
                <x-secondary-button type="button" wire:click="borrarFiltro" class="ml-1" title="{{ __('Borrar filtro') }}">
                    <i class="fa-solid fa-filter-circle-xmark mr-1"></i>{{ __('Borrar filtro') }}
                </x-secondary-button>
                @include('livewire.parciales.selector-columnas')
                {{-- Acciones en lote: actúan sobre los marcados si hay alguno y, si no, sobre
                     todo lo que cumple el filtro. En menú porque esto va a crecer. --}}
                <span class="ml-1 inline-block align-middle">
                    <x-dropdown align="right" width="60">
                        <x-slot name="trigger">
                            <button type="button" title="{{ __('Acciones en lote') }}"
                                class="p-2 rounded-lg text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white hover:bg-zinc-800/5 dark:hover:bg-white/10">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <x-dropdown-link href="#" wire:click="abrirCobro">
                                <i class="fa-solid fa-hand-holding-dollar mr-1"></i>{{ __('Cobrar') }}
                                @if (count($seleccionados))
                                    ({{ count($seleccionados) }})
                                @endif
                            </x-dropdown-link>
                            {{-- Solo donde hay contabilidad con la que enlazar; los recibos
                                 que ya tienen asiento se saltan solos. --}}
                            @if (contabilidad_activa())
                                <x-dropdown-link href="#" wire:click="enlazarContabilidad">
                                    <i class="fa-solid fa-link mr-1"></i>{{ __('Enlazar con contabilidad') }}
                                    @if (count($seleccionados))
                                        ({{ count($seleccionados) }})
                                    @endif
                                </x-dropdown-link>
                            @endif
                        </x-slot>
                    </x-dropdown>
                </span>
            </x-slot>

            <div class="py-3 px-6 flex items-center">
                @include('livewire.parciales.lineas_x_pagina')
                @include('livewire.parciales.buscador', ['placeholder' => "Inmueble (tipo, planta o puerta) o propietario"])
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
                            @if ($this->verColumna('inmueble'))
                                <th class="py-3 px-6">{{ __('Inmueble') }}</th>
                            @endif
                            @if ($this->verColumna('propietario'))
                                <th class="py-3 px-6">{{ __('Propietario') }}</th>
                            @endif
                            @if ($this->verColumna('presupuesto'))
                                <th class="py-3 px-6">{{ __('Presupuesto') }}</th>
                            @endif
                            @if ($this->verColumna('numero_pago'))
                                <th class="cursor-pointer py-3 px-6" wire:click="ordenar('numero_pago')">
                                    {{ __('Pago') }}
                                    @if ($sort == 'numero_pago')
                                        <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                    @else
                                        <i class="fa-solid fa-sort float-right mt-1"></i>
                                    @endif
                                </th>
                            @endif
                            @if ($this->verColumna('vencimiento'))
                                <th class="cursor-pointer py-3 px-6" wire:click="ordenar('fecha_vencimiento')">
                                    {{ __('Vencimiento') }}
                                    @if ($sort == 'fecha_vencimiento')
                                        <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                    @else
                                        <i class="fa-solid fa-sort float-right mt-1"></i>
                                    @endif
                                </th>
                            @endif
                            @if ($this->verColumna('importe'))
                                <th class="cursor-pointer py-3 px-6 text-right" wire:click="ordenar('importe')">
                                    {{ __('Importe') }}
                                    @if ($sort == 'importe')
                                        <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} ml-1"></i>
                                    @else
                                        <i class="fa-solid fa-sort ml-1"></i>
                                    @endif
                                </th>
                            @endif
                            @if ($this->verColumna('importe_pagado'))
                                <th class="cursor-pointer py-3 px-6 text-right" wire:click="ordenar('importe_pagado')">
                                    {{ __('Pagado') }}
                                    @if ($sort == 'importe_pagado')
                                        <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} ml-1"></i>
                                    @else
                                        <i class="fa-solid fa-sort ml-1"></i>
                                    @endif
                                </th>
                            @endif
                            @if ($this->verColumna('saldo'))
                                <th class="cursor-pointer py-3 px-6 text-right" wire:click="ordenar('saldo')">
                                    {{ __('Saldo') }}
                                    @if ($sort == 'saldo')
                                        <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} ml-1"></i>
                                    @else
                                        <i class="fa-solid fa-sort ml-1"></i>
                                    @endif
                                </th>
                            @endif
                            @if ($this->verColumna('forma_de_pago'))
                                <th class="py-3 px-6">{{ __('Forma de pago') }}</th>
                            @endif
                            @if ($this->verColumna('estado'))
                                <th class="py-3 px-6">{{ __('Estado') }}</th>
                            @endif
                            @if ($this->verColumna('asiento'))
                                <th class="py-3 px-6">{{ __('Asiento') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                <td class="px-6 py-4">
                                    <input type="checkbox" wire:model.live="seleccionados" value="{{ $item->id }}" />
                                </td>
                                @if ($this->verColumna('inmueble'))
                                    <td class="px-6 py-4">
                                        {{ $item->inmueble?->tipoInmueble?->descripcion }}
                                        {{ $item->inmueble?->planta }} {{ $item->inmueble?->puerta }}
                                    </td>
                                @endif
                                @if ($this->verColumna('propietario'))
                                    <td class="px-6 py-4">
                                        <span class="mayusculas">{{ $item->propietario?->persona?->nombreCompleto }}</span>
                                    </td>
                                @endif
                                @if ($this->verColumna('presupuesto'))
                                    <td class="px-6 py-4">
                                        {{ $item->presupuesto?->nombre }} ({{ $item->presupuesto?->anho }})
                                    </td>
                                @endif
                                @if ($this->verColumna('numero_pago'))
                                    <td class="px-6 py-4">{{ $item->numero_pago }}</td>
                                @endif
                                @if ($this->verColumna('vencimiento'))
                                    <td class="px-6 py-4">{{ $item->fecha_vencimiento?->format('d/m/Y') }}</td>
                                @endif
                                @if ($this->verColumna('importe'))
                                    <td class="px-6 py-4 text-right">{{ number_format($item->importe, 2, ',', '.') }} €</td>
                                @endif
                                @if ($this->verColumna('importe_pagado'))
                                    <td class="px-6 py-4 text-right">{{ number_format($item->importe_pagado, 2, ',', '.') }} €</td>
                                @endif
                                @if ($this->verColumna('saldo'))
                                    <td @class([
                                        'px-6 py-4 text-right',
                                        'text-red-600 dark:text-red-400' => $item->saldo > 0,
                                    ])>
                                        {{ number_format($item->saldo, 2, ',', '.') }} €
                                    </td>
                                @endif
                                @if ($this->verColumna('forma_de_pago'))
                                    <td class="px-6 py-4">{{ $item->formaDePago?->descripcion }}</td>
                                @endif
                                @if ($this->verColumna('estado'))
                                    <td class="px-6 py-4">
                                        {{ $item->estado?->descripcion }}
                                        @if ($item->historial_estados_count > 1)
                                            <button type="button" wire:click="verHistorial({{ $item->id }})"
                                                class="ml-2 text-gray-500 hover:text-gray-800" title="{{ __('Historial de estados') }}">
                                                <i class="fa-solid fa-clock-rotate-left"></i>
                                            </button>
                                        @endif
                                    </td>
                                @endif
                                @if ($this->verColumna('asiento'))
                                    {{-- Los recibos del mismo vencimiento comparten asiento. --}}
                                    <td class="px-6 py-4">
                                        {{ $item->asiento_contable ?? '—' }}
                                    </td>
                                @endif
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

        @include('livewire.parciales.modal-historial-estado')

        {{-- Cobro a mano: el que no va por remesa (transferencia, efectivo). Cobra el
             pendiente completo de cada recibo; los ya pagados se quedan como están. --}}
        <x-dosl.dialog-modal wire:model.live="cobroAbierto" maxWidth="lg">
            <x-slot name="title">
                {{ __('Marcar recibos como cobrados') }}
            </x-slot>

            <x-slot name="content">
                <div class="mb-4">
                    <x-label>{{ __('Recibos') }}:</x-label>
                    <p class="font-medium">{{ count($cobroIds) }}</p>
                </div>

                <div class="mb-4">
                    <x-label for="cobroFecha">{{ __('Fecha del cobro') }}:</x-label>
                    <x-input class="block mt-1 w-full" type="date" id="cobroFecha" wire:model="cobroFecha" />
                    <x-input-error for="cobroFecha" class="mt-1" />
                </div>

                <div>
                    <x-label for="cobroFormaDePagoId">{{ __('Forma de pago') }}:</x-label>
                    <x-select id="cobroFormaDePagoId" class="block mt-1 w-full py-3" wire:model="cobroFormaDePagoId">
                        @foreach ($formasDePago as $id => $descripcion)
                            <option value="{{ $id }}">{{ $descripcion }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error for="cobroFormaDePagoId" class="mt-1" />
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button type="button" wire:click="$set('cobroAbierto', false)">
                    {{ __('Cancelar') }}
                </x-secondary-button>
                <x-button type="button" class="ml-2" wire:click="cobrar">
                    {{ __('Sí, cobrar') }}
                </x-button>
            </x-slot>
        </x-dosl.dialog-modal>
    </x-slot>
</x-botonera-page>

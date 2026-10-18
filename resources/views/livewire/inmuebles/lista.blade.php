<x-botonera-page>
    <x-slot name="title">
        {{ __('Inmuebles') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Inmuebles de las comunidades') }}
    </x-slot>
    <x-slot name="botonera">
        <a href="{{ route('inmuebles.crear') }}"
            wire:navigate class="btn btn-nuevo" id="btn-nuevo-inmueble" title="{{ __('Nuevo') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
        </a>
        @include('livewire.parciales.boton-acceso-directo')
    </x-slot>

    <x-slot name="content">
        @if ($borradores->count())
            <x-dosl.tabla>
                <div class="py-3 px-6 flex items-center gap-2 text-red-700 dark:text-red-400">
                    <i class="fa-solid fa-bell"></i>
                    <span class="font-semibold">{{ __('Inmuebles sin terminar') }}</span>
                </div>
                <table class="table-striped w-full table-auto text-sm text-left">
                    <thead class="font-medium border-b">
                        <tr>
                            <th class="py-3 px-6">{{ __('Tipo') }}</th>
                            <th class="py-3 px-6">{{ __('Planta / Puerta') }}</th>
                            <th class="py-3 px-6">{{ __('Modificado') }}</th>
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($borradores as $borrador)
                            <tr wire:key="borrador-{{ $borrador->id }}">
                                <td class="px-6 py-4">{{ $borrador->tipoInmuebleDescripcion }}</td>
                                <td class="px-6 py-4">{{ $borrador->planta }} / {{ $borrador->puerta }}</td>
                                <td class="px-6 py-4">{{ $borrador->updated_at->format('d-m-Y H:i') }}</td>
                                <td class="px-4 whitespace-nowrap">
                                    <a href="{{ route('inmuebles.crear', ['borrador' => $borrador->id]) }}" wire:navigate
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
                <span class="ml-1 inline-block align-middle">
                    <x-dropdown align="right" width="60">
                        <x-slot name="trigger">
                            <button type="button" title="{{ __('Acciones en lote') }}"
                                class="p-2 rounded-lg text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white hover:bg-zinc-800/5 dark:hover:bg-white/10">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            {{-- Una hoja por inmueble marcado, con su piso y a nombre de quien
                                 paga sus recibos; sin ninguno, una en blanco. --}}
                            <x-dropdown-link target="_blank" id="btn-plantilla-mandato-sepa"
                                href="{{ route('mandatos-sepa.plantilla-lote', array_filter(['inmuebles' => implode(',', $seleccionados)])) }}">
                                <i class="fa-solid fa-file-signature mr-1"></i>{{ __('Plantilla mandato SEPA') }}
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
                @include('livewire.parciales.buscador', ['placeholder' => "Puerta, referencia catastral o comunidad"])
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
                            <th class="py-3 px-6">{{ __('Tipo') }}</th>
                            <th class="py-3 px-6">{{ __('Ocupación') }}</th>
                            <th class="py-3 px-6">{{ __('Planta') }}</th>
                            <th class="py-3 px-6">{{ __('Puerta') }}</th>
                            <th class="py-3 px-6">
                                {{ __('Coeficiente') }}
                                <div
                                    @class([
                                        'font-normal text-xs',
                                        'text-green-600 dark:text-green-400' => $sumaCoeficientes == 100,
                                        'text-red-600 dark:text-red-400' => $sumaCoeficientes < 100,
                                        'text-red-600 dark:text-red-400 animate-pulse' => $sumaCoeficientes > 100,
                                    ])
                                >
                                    {{ __('Suma') }}: {{ number_format($sumaCoeficientes, 3) }}%
                                </div>
                            </th>
                            <th class="py-3 px-6">{{ __('Propietarios') }}</th>
                            <th class="py-3 px-6">{{ __('Forma de pago') }}</th>
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                <td class="px-6 py-4">
                                    <input type="checkbox" wire:model.live="seleccionados" value="{{ $item->id }}" />
                                </td>
                                <td class="px-6 py-4">{{ $item->tipoInmueble->descripcion }}</td>
                                <td class="px-6 py-4">{{ $item->ocupacion->descripcion }}</td>
                                <td class="px-6 py-4">{{ $item->planta }}</td>
                                <td class="px-6 py-4">{{ $item->puerta }}</td>
                                <td class="px-6 py-4">{{ $item->coeficiente }}%</td>
                                <td class="px-6 py-4">
                                    {{ $item->propietarios->map(fn ($p) => $p->persona->nombreCompleto)->join(', ') }}
                                    @if ($item->propietarios->contains(fn ($p) => $p->correo()?->estaValidado()))
                                        <i class="fa-solid fa-envelope text-green-600 ml-1" title="{{ __('Algún propietario tiene el correo validado') }}"></i>
                                    @else
                                        <i class="fa-solid fa-envelope text-red-600 ml-1" title="{{ __('Ningún propietario tiene el correo validado') }}"></i>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @if ($item->formaPagoVigente?->formaDePago)
                                        {{ $item->formaPagoVigente->formaDePago->descripcion }}
                                        @if ($item->formaPagoVigente->forma_de_pago_id === \App\Models\FormaDePago::RECIBO_BANCARIO)
                                            @if ($item->formaPagoVigente->cuentaBancaria?->mandatoActivo?->documentos->isNotEmpty())
                                                <i class="fa-solid fa-paperclip text-green-600 ml-1" title="{{ __('Mandato SEPA con documento adjunto') }}"></i>
                                            @else
                                                <i class="fa-solid fa-paperclip text-red-600 ml-1" title="{{ __('Sin documento de mandato SEPA adjunto') }}"></i>
                                            @endif
                                        @endif
                                    @else
                                        <span class="text-amber-600 dark:text-amber-400">{{ __('Sin asignar') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 whitespace-nowrap">
                                    <a href="{{ route('inmuebles.editar', $item) }}" wire:navigate class="btn-editar"
                                        id="btn-editar-inmueble-{{ $item->id }}" title="{{ __('Modificar') }}">
                                        <i class="fa-solid fa-pen"> </i>
                                    </a>
                                    <x-button type="button" class="bg-blue-600 hover:bg-blue-700 text-white ml-1"
                                        wire:click="duplicar({{ $item->id }})"
                                        id="btn-duplicar-inmueble-{{ $item->id }}" title="{{ __('Duplicar') }}">
                                        <i class="fa-solid fa-copy"> </i>
                                    </x-button>
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

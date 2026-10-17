<x-botonera-page>
    <x-slot name="title">
        {{ __('Convocatorias de junta') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Convocatorias de junta de la comunidad') }}
    </x-slot>
    <x-slot name="botonera">
        <x-button type="button" class="btn btn-nuevo" id="btn-nueva-convocatoria-junta"
            wire:click="$dispatch('abrir-crear-convocatoria-junta')" title="{{ __('Nueva') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nueva') }}
        </x-button>
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
                {{-- La carta se abre en otra pestaña: al volver a esta, se refresca para que el
                     icono de la impresora pase a ser el del PDF ya generado. --}}
                <table class="table-striped w-full table-auto text-sm text-left"
                    x-data x-on:visibilitychange.document="if (! document.hidden) $wire.$refresh()">
                    <thead class="font-medium border-b">
                        <tr>
                            @foreach ($this->columnas as $clave)
                                @switch($clave)
                                    @case('fecha')
                                        <th class="cursor-pointer py-3 px-6" wire:click="ordenar('fecha')">
                                            {{ __('Fecha') }}
                                            @if ($sort == 'fecha')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
                                        @break
                                    @case('tipo')
                                        <th class="py-3 px-6">{{ __('Tipo') }}</th>
                                        @break
                                    @case('primera')
                                        <th class="py-3 px-6">{{ __('Primera convocatoria') }}</th>
                                        @break
                                    @case('segunda')
                                        <th class="py-3 px-6">{{ __('Segunda convocatoria') }}</th>
                                        @break
                                    @case('puntos')
                                        <th class="py-3 px-6 text-right">{{ __('Puntos del orden del día') }}</th>
                                        @break
                                    @case('carta')
                                        <th class="py-3 px-6">{{ __('Fecha de la carta') }}</th>
                                        @break
                                @endswitch
                            @endforeach
                            <th class="py-3 px-6">{{ __('Acción') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($items as $item)
                            <tr wire:key="{{ $item->id }}">
                                @foreach ($this->columnas as $clave)
                                    @switch($clave)
                                        @case('fecha')
                                            <td class="px-6 py-4">{{ $item->fecha?->format('d/m/Y') }}</td>
                                            @break
                                        @case('tipo')
                                            <td class="px-6 py-4">{{ $item->tipoJunta?->descripcion }}</td>
                                            @break
                                        @case('primera')
                                            <td class="px-6 py-4">{{ substr($item->hora_primera_convocatoria, 0, 5) }}</td>
                                            @break
                                        @case('segunda')
                                            <td class="px-6 py-4">{{ substr($item->hora_segunda_convocatoria, 0, 5) }}</td>
                                            @break
                                        @case('puntos')
                                            <td class="px-6 py-4 text-right">{{ $item->lineas_orden_del_dia_count }}</td>
                                            @break
                                        @case('carta')
                                            <td class="px-6 py-4">{{ $item->fecha_carta?->format('d/m/Y') ?? '—' }}</td>
                                            @break
                                    @endswitch
                                @endforeach
                                <td class="px-4 whitespace-nowrap">
                                    {{-- Sin carta: la impresora la genera. Con carta: impresora+PDF la enseña, y con
                                         Mayúsculas pulsada la regenera (fecha de hoy, datos de ahora). --}}
                                    @php($urlCarta = route('convocatorias-juntas.carta', $item))
                                    @if ($item->tieneCarta())
                                        <a href="{{ $urlCarta }}" target="_blank" class="btn-editar"
                                            id="btn-carta-convocatoria-junta-{{ $item->id }}"
                                            x-data="{ shift: false }" @keydown.shift.window="shift = true"
                                            @keyup.shift.window="shift = false" x-on:blur.window="shift = false"
                                            x-on:click="if ($event.shiftKey) { $event.preventDefault(); window.open('{{ $urlCarta }}?regenerar=1', '_blank') }"
                                            x-bind:title="shift ? '{{ __('Regenerar la convocatoria con fecha de hoy') }}' : '{{ __('Ver la convocatoria (Mayúsculas: regenerarla)') }}'">
                                            <i class="fa-solid" x-bind:class="shift ? 'fa-arrows-rotate' : 'fa-print'"></i><i
                                                x-show="! shift" class="fa-solid fa-file-pdf" style="font-size: .7em; margin-left: 1px"></i>
                                        </a>
                                    @else
                                        <a href="{{ $urlCarta }}" target="_blank" class="btn-editar"
                                            id="btn-carta-convocatoria-junta-{{ $item->id }}" title="{{ __('Generar la convocatoria') }}">
                                            <i class="fa-solid fa-print"></i>
                                        </a>
                                    @endif
                                    {{-- Correo: la carta de cada inmueble a sus propietarios (ver EnviarCartas). --}}
                                    <x-button type="button" class="bg-blue-600 hover:bg-blue-700 text-white ml-1"
                                        id="btn-correo-convocatoria-junta-{{ $item->id }}"
                                        wire:click="$dispatch('abrir-envio-convocatoria-junta', {id: {{ $item->id }}})"
                                        title="{{ __('Enviar la convocatoria por correo') }}">
                                        <i class="fa-solid fa-envelope"> </i>
                                    </x-button>
                                    <x-button type="button" class="btn-editar ml-1" id="btn-editar-convocatoria-junta-{{ $item->id }}"
                                        wire:click="$dispatch('convocatoria-junta-editar', {id: {{ $item->id }}})"
                                        title="{{ __('Modificar') }}">
                                        <i class="fa-solid fa-pen"> </i>
                                    </x-button>
                                    <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white ml-1"
                                        id="btn-eliminar-convocatoria-junta-{{ $item->id }}"
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

        @livewire('convocatorias-juntas.formulario')
        @livewire('convocatorias-juntas.enviar-cartas')
    </x-slot>
</x-botonera-page>

<x-botonera-page>
    <x-slot name="title">
        {{ __('Exámenes') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Calendario de exámenes del horario') }}
    </x-slot>
    <x-slot name="botonera">
        <x-button type="button" class="btn btn-nuevo" id="btn-nuevo-examen"
            wire:click="$dispatch('abrir-crear-examen')" title="{{ __('Nuevo') }}">
            <i class="fa-solid fa-plus"> </i>{{ __('Nuevo') }}
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
                {{-- Lo que muestra la lista (sus filtros), por meses, en otra pestaña y sin el horario. --}}
                <x-secondary-button type="button" title="{{ __('Imprimir') }}"
                    onclick="window.open('{{ route('examenes.pdf', ['periodo' => $filtros['periodo'] ?? null, 'asignatura_id' => ($filtros['asignatura_id'] ?? 0) ?: null]) }}', '_blank')">
                    <i class="fa-solid fa-print mr-1"></i>{{ __('Imprimir') }}
                </x-secondary-button>
                <x-secondary-button type="button" id="btn-suscribirse-examenes" wire:click="abrirSuscripcion"
                    title="{{ __('Suscribirse al calendario de exámenes') }}">
                    <i class="fa-solid fa-calendar-plus mr-1"></i>{{ __('Suscribirse') }}
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
                                    @case('dia')
                                        <th class="py-3 px-6">{{ __('Día') }}</th>
                                        @break
                                    @case('hora')
                                        <th class="cursor-pointer py-3 px-6" wire:click="ordenar('hora')">
                                            {{ __('Hora') }}
                                            @if ($sort == 'hora')
                                                <i class="fa-solid fa-sort-{{ $direction == 'asc' ? 'up' : 'down' }} float-right mt-1"></i>
                                            @else
                                                <i class="fa-solid fa-sort float-right mt-1"></i>
                                            @endif
                                        </th>
                                        @break
                                    @case('hora_fin')
                                        <th class="py-3 px-6">{{ __('Hora fin') }}</th>
                                        @break
                                    @case('asignatura')
                                        <th class="py-3 px-6">{{ __('Asignatura') }}</th>
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
                                            <td class="px-6 py-4">{{ $item->fecha->format('d/m/Y') }}</td>
                                            @break
                                        @case('dia')
                                            <td class="px-6 py-4">{{ \App\Support\DiaSemana::deFecha($item->fecha)->nombre() }}</td>
                                            @break
                                        @case('hora')
                                            <td class="px-6 py-4">{{ substr($item->hora, 0, 5) }}</td>
                                            @break
                                        @case('hora_fin')
                                            <td class="px-6 py-4">{{ substr($item->hora_fin, 0, 5) }}</td>
                                            @break
                                        @case('asignatura')
                                            <td class="px-6 py-4">{{ $item->asignatura?->nombreConModulo() }}</td>
                                            @break
                                    @endswitch
                                @endforeach
                                <td class="px-4 whitespace-nowrap">
                                    <x-button type="button" class="btn-editar" id="btn-editar-examen-{{ $item->id }}"
                                        wire:click="$dispatch('examen-editar', {id: {{ $item->id }}})"
                                        title="{{ __('Modificar') }}">
                                        <i class="fa-solid fa-pen"> </i>
                                    </x-button>
                                    <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white ml-1"
                                        id="btn-eliminar-examen-{{ $item->id }}"
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

        @livewire('examenes.formulario')

        <x-dosl.dialog-modal wire:model.live="verSuscripcion" class="backdrop-blur" maxWidth="2xl">
            <x-slot name="title">
                {{ __('Suscribirse al calendario de exámenes') }}
            </x-slot>

            <x-slot name="content">
                @php($webcal = preg_replace('#^https?://#', 'webcal://', $urlCalendario))
                <p class="text-sm">
                    {{ __('Quien se suscriba a esta dirección verá los exámenes de este horario en su calendario, y los cambios que se hagan aquí le llegarán solos.') }}
                </p>

                {{-- copiar(): clipboard moderno cuando el navegador lo deja (hace falta https),
                     y si no, el textarea de toda la vida. --}}
                <div class="mt-4" x-data="{
                        copiado: false,
                        copiar(texto) {
                            const hecho = () => { this.copiado = true; setTimeout(() => this.copiado = false, 2000) };
                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(texto).then(hecho);
                                return;
                            }
                            const area = document.createElement('textarea');
                            area.value = texto;
                            area.style.position = 'fixed';
                            area.style.opacity = 0;
                            document.body.appendChild(area);
                            area.select();
                            document.execCommand('copy');
                            document.body.removeChild(area);
                            hecho();
                        }
                    }">
                    <x-label :value="__('Dirección del calendario')" />
                    <p class="mt-1 font-mono text-xs break-all border rounded px-2 py-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                        title="{{ __('Pulse para copiar') }}" x-on:click="copiar(@js($urlCalendario))">
                        {{ $urlCalendario }}
                        <i class="fa-regular fa-copy ml-1 text-gray-500"></i>
                    </p>
                    <span x-show="copiado" x-transition class="text-sm text-green-700 dark:text-green-400">
                        {{ __('Copiado') }}
                    </span>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="https://calendar.google.com/calendar/render?cid={{ urlencode($webcal) }}" target="_blank" rel="noopener"
                        class="btn btn-nuevo">
                        <i class="fa-brands fa-google mr-1"></i>{{ __('Añadir a Google Calendar') }}
                    </a>
                    <a href="{{ $webcal }}" class="btn btn-nuevo">
                        <i class="fa-brands fa-apple mr-1"></i>{{ __('Abrir en el calendario del iPhone o Mac') }}
                    </a>
                </div>

                <div class="mt-4 text-sm space-y-2">
                    <p>
                        <span class="font-semibold">{{ __('Google Calendar') }}:</span>
                        {{ __('desde el ordenador, en calendar.google.com, «Otros calendarios» → «+» → «Desde URL», y pegue la dirección. Después sale también en el móvil.') }}
                    </p>
                    <p>
                        <span class="font-semibold">{{ __('iPhone') }}:</span>
                        {{ __('Ajustes → Calendario → Cuentas → Añadir cuenta → Otra → Añadir calendario suscrito, y pegue la dirección.') }}
                    </p>
                    <p class="text-gray-500">
                        {{ __('Google vuelve a leer el calendario cada varias horas: un examen nuevo o cambiado puede tardar en aparecer.') }}
                    </p>
                    <p class="text-gray-500">
                        {{ __('Quien tenga la dirección puede ver los exámenes sin entrar en la aplicación: compártala solo con quien corresponda.') }}
                    </p>
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-dosl.boton-cerrar accion="cerrarSuscripcion" />
            </x-slot>
        </x-dosl.dialog-modal>
    </x-slot>
</x-botonera-page>

<x-botonera-page>
    <x-slot name="title">
        {{ __('Horario semanal') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Asignatura de cada sesión, por día') }}
    </x-slot>
    <x-slot name="botonera">
        @if (count($diasConfig))
            {{-- El modal de edición es una columna por día, como la rejilla: con el móvil en
                 vertical no cabe, así que ahí no se ofrece (mismo corte que la vista de un día). --}}
            <x-button type="button" class="btn btn-editar max-md:portrait:hidden" id="btn-editar-rejilla"
                wire:click="$dispatch('abrir-editar-rejilla')" title="{{ __('Editar') }}">
                <i class="fa-solid fa-pen"> </i><span class="hidden sm:inline">{{ __('Editar') }}</span>
            </x-button>
        @endif
    </x-slot>

    <x-slot name="content">
        <div class="py-6 px-4 sm:px-6 lg:px-8">
            @if (! count($diasConfig))
                <div class="rounded-lg bg-yellow-100 border border-yellow-300 px-4 py-3 text-sm text-yellow-800 dark:bg-yellow-900/40 dark:border-yellow-700 dark:text-yellow-200">
                    {{ __('Todavía no hay días de clase configurados.') }}
                    <a href="{{ route('dias-sesiones.edit') }}" class="underline font-medium">{{ __('Configura primero los días y sesiones.') }}</a>
                </div>
            @else
                <x-dosl.tabla>
                    <x-slot name="botonera">
                        <a href="{{ route('rejilla.pdf') }}" target="_blank">
                            <x-secondary-button type="button" id="btn-imprimir-rejilla" title="{{ __('Imprimir / Descargar PDF') }}">
                                <i class="fa-solid fa-print mr-1"></i>{{ __('Imprimir') }}
                            </x-secondary-button>
                        </a>
                    </x-slot>

                    {{-- Con el móvil en vertical no caben los días en columnas: se ve un día
                         cada vez, elegido en las pestañas. En horizontal (y en una tableta,
                         aunque esté en vertical) se ve la semana entera. --}}
                    <div class="hidden max-md:portrait:block" x-data="{ dia: {{ $diaInicial }} }">
                        <div class="flex border-b">
                            @foreach ($diasConfig as $dia => $config)
                                <button type="button" x-on:click="dia = {{ $dia }}" title="{{ $config['nombre'] }}"
                                    :class="dia === {{ $dia }} ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400' : 'border-transparent'"
                                    class="flex-1 py-2 text-sm font-semibold border-b-2">
                                    {{ $config['abreviatura'] }}
                                </button>
                            @endforeach
                        </div>
                        @foreach ($diasConfig as $dia => $config)
                            <div wire:key="dia-movil-{{ $dia }}" x-show="dia === {{ $dia }}" @if ($dia !== $diaInicial) x-cloak @endif>
                                <div class="px-4 py-2 font-medium border-b">{{ $config['nombre'] }}</div>
                                <div class="divide-y">
                                    @if ($escala)
                                        {{-- Profesor: sesiones a su hora real, recreos y huecos libres, por orden. --}}
                                        @foreach ($escala['columnas'][$dia]['tramos'] ?? [] as $tramo)
                                            <div class="flex items-center gap-3 px-4 py-2">
                                                <span class="shrink-0 text-xs font-semibold whitespace-nowrap">
                                                    {{ $tramo['hora'] }}–{{ $tramo['fin_hora'] }}
                                                </span>
                                                <div class="flex-1">
                                                    @if ($tramo['tipo'] === 'sesion')
                                                        <div class="rounded px-2 py-1 text-xs" style="background-color: {{ $tramo['fondo'] }}; color: {{ $tramo['texto'] }};">
                                                            {{ $tramo['nombre'] }}@if ($tramo['aula']) · {{ $tramo['aula'] }}@endif
                                                            @if ($tramo['modulo'])
                                                                <div class="italic">{{ $tramo['modulo'] }}</div>
                                                            @endif
                                                        </div>
                                                    @elseif ($tramo['tipo'] === 'recreo')
                                                        <div class="rounded px-2 py-1 text-xs text-center bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                                            {{ __('Recreo') }}
                                                        </div>
                                                    @else
                                                        <div class="rounded px-2 py-1 text-xs text-gray-400 dark:text-gray-500">
                                                            {{ __('Libre') }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        @foreach ($config['slots'] as $slot)
                                            <div class="flex items-center gap-3 px-4 py-2">
                                                <span class="shrink-0 text-xs font-semibold whitespace-nowrap">
                                                    {{ $slot['hora'] }}–{{ $slot['fin'] }}
                                                </span>
                                                <div class="flex-1">
                                                    @include('livewire.horarios.parciales.celda-rejilla')
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="max-md:portrait:hidden overflow-x-auto">
                        @if ($escala)
                            @include('livewire.horarios.parciales.rejilla-profesor')
                        @else
                            <table class="table-striped w-full table-auto text-sm text-left">
                                <thead class="font-medium border-b">
                                    <tr>
                                        <th class="py-3 px-4">{{ __('Horas') }}</th>
                                        @foreach ($diasConfig as $dia => $config)
                                            <th class="py-3 px-4">{{ $config['nombre'] }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @for ($fila = 0; $fila < $maxSlots; $fila++)
                                        <tr wire:key="fila-{{ $fila }}">
                                            <td class="px-2 py-2 align-top text-xs font-semibold whitespace-nowrap">
                                                @if ($horasFilas[$fila] ?? null)
                                                    {{ $horasFilas[$fila]['inicio'] }}–{{ $horasFilas[$fila]['fin'] }}
                                                @endif
                                            </td>
                                            @foreach ($diasConfig as $dia => $config)
                                                @php($slot = $config['slots'][$fila] ?? null)
                                                <td class="px-2 py-2 align-top">
                                                    @if ($slot)
                                                        @include('livewire.horarios.parciales.celda-rejilla')
                                                    @endif
                                                    {{-- sin $slot: este día no tiene sesión a esta hora --}}
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endfor
                                </tbody>
                            </table>
                        @endif
                    </div>
                </x-dosl.tabla>
            @endif
        </div>

        @livewire('horarios.rejilla-formulario')
    </x-slot>
</x-botonera-page>

<x-botonera-page>
    <x-slot name="title">
        {{ __('Horario semanal') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Asignatura de cada sesión, por día') }}
    </x-slot>
    <x-slot name="botonera">
        @if (count($diasConfig))
            <x-button type="button" class="btn btn-editar" id="btn-editar-rejilla"
                wire:click="$dispatch('abrir-editar-rejilla')" title="{{ __('Editar') }}">
                <i class="fa-solid fa-pen"> </i>{{ __('Editar') }}
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

                    <div class="overflow-x-auto">
                        <table class="table-striped w-full table-auto text-sm text-left">
                            <thead class="font-medium border-b">
                                <tr>
                                    @foreach ($diasConfig as $dia => $config)
                                        <th class="py-3 px-4">{{ $config['nombre'] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @for ($fila = 0; $fila < $maxSlots; $fila++)
                                    <tr wire:key="fila-{{ $fila }}">
                                        @foreach ($diasConfig as $dia => $config)
                                            @php($slot = $config['slots'][$fila] ?? null)
                                            <td class="px-2 py-2 align-top">
                                                @if (! $slot)
                                                    {{-- este día ya no tiene más filas --}}
                                                @elseif ($slot['tipo'] === 'recreo')
                                                    <div class="rounded px-2 py-1 text-xs text-center bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                                        <div class="font-semibold">{{ $slot['hora'] }}</div>
                                                        <div>{{ __('Recreo') }} ({{ $slot['duracion'] }} min)</div>
                                                    </div>
                                                @elseif (isset($asignaciones[$dia][$slot['numero']]))
                                                    <div class="rounded px-2 py-1 text-xs"
                                                        style="background-color: {{ $asignaciones[$dia][$slot['numero']]['fondo'] }}; color: {{ $asignaciones[$dia][$slot['numero']]['texto'] }};">
                                                        <div class="font-semibold">{{ $slot['hora'] }}</div>
                                                        <div>{{ $asignaciones[$dia][$slot['numero']]['nombre'] }}</div>
                                                    </div>
                                                @else
                                                    <div class="rounded px-2 py-1 text-xs text-gray-400 dark:text-gray-500">
                                                        <div class="font-semibold">{{ $slot['hora'] }}</div>
                                                        <div>—</div>
                                                    </div>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>
                </x-dosl.tabla>
            @endif
        </div>

        @livewire('horarios.rejilla-formulario')
    </x-slot>
</x-botonera-page>

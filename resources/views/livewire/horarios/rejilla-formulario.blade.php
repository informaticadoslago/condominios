<div>
    <x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="7xl">
        <x-slot name="title">
            {{ __('Horario semanal') }}
        </x-slot>

        <x-slot name="content">
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
                                    <td class="px-4 py-3 align-top">
                                        @if (! $slot)
                                            {{-- este día ya no tiene más filas --}}
                                        @elseif ($slot['tipo'] === 'recreo')
                                            <div class="rounded px-2 py-1 text-xs text-center bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                                <div class="font-semibold">{{ $slot['hora'] }}</div>
                                                <div>{{ __('Recreo') }} ({{ $slot['duracion'] }} min)</div>
                                            </div>
                                        @else
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                                                {{ $slot['hora'] }}
                                            </div>
                                            <select wire:model="asignaciones.{{ $dia }}.{{ $slot['numero'] }}" class="w-48">
                                                <option value="">{{ __('— Ninguna —') }}</option>
                                                @foreach ($asignaturas as $asignatura)
                                                    <option value="{{ $asignatura->id }}">
                                                        {{ $asignatura->nombreConModulo() }}{{ $asignatura->estado_id != \App\Models\Asignatura::ESTADO_ACTIVO ? ' ('.__('baja').')' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @if ($esDeProfesor || $conAvisos)
                                                <div class="mt-1 flex items-center gap-1">
                                                    @if ($esDeProfesor)
                                                        {{-- Vacío = la hora que le toca por la jornada ($slot['hora']). --}}
                                                        <x-input type="time" step="300" class="w-32 text-xs"
                                                            wire:model="horas.{{ $dia }}.{{ $slot['numero'] }}"
                                                            title="{{ __('Hora de inicio a mano (vacío = :hora)', ['hora' => $slot['hora']]) }}" />
                                                    @endif
                                                    @if ($conAvisos && isset($sesionesGuardadas[$dia][$slot['numero']]))
                                                        {{-- Los avisos push del usuario en esta sesión: solo si está guardada con
                                                             asignatura, y se esconde si se quita en el desplegable. --}}
                                                        @php($conAviso = isset($avisos[$dia][$slot['numero']]))
                                                        <button type="button" wire:click="abrirAviso({{ $dia }}, {{ $slot['numero'] }})"
                                                            x-show="$wire.asignaciones[{{ $dia }}][{{ $slot['numero'] }}]"
                                                            title="{{ __('Avisos') }}"
                                                            class="p-1 {{ $conAviso ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300' }}">
                                                            <i class="{{ $conAviso ? 'fa-solid' : 'fa-regular' }} fa-bell"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                                @if ($esDeProfesor)
                                                    <x-input-error for="horas.{{ $dia }}.{{ $slot['numero'] }}" class="mt-1" />
                                                @endif
                                            @endif
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-dosl.boton-cerrar />
            <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
                title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
        </x-slot>
    </x-dosl.dialog-modal>

    {{-- Modal secundario: los avisos push de una sesión. Son del usuario, no del horario: se
         guardan con su propio botón, no con el Guardar de la rejilla. --}}
    <x-dosl.dialog-modal wire:model.live="avisoAbierto" maxWidth="lg">
        <x-slot name="title">
            {{ __('Avisos') }}
        </x-slot>
        <x-slot name="subtitulo">
            {{ $avisoSesion }}
        </x-slot>

        <x-slot name="content">
            <div class="grid sm:grid-cols-2 gap-4">
                @foreach (['avisoInicio' => __('Aviso de inicio'), 'avisoFin' => __('Aviso de fin')] as $campo => $etiqueta)
                    <div>
                        <x-label for="{{ $campo }}" :value="$etiqueta" />
                        <x-select id="{{ $campo }}" class="block mt-1 w-full py-3" wire:model="{{ $campo }}">
                            <option value="">{{ __('Ninguno') }}</option>
                            @foreach (\App\Models\HorarioSesionAviso::opciones() as $minutos => $texto)
                                <option value="{{ $minutos }}">{{ $texto }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error for="{{ $campo }}" class="mt-2" />
                    </div>
                @endforeach
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-dosl.boton-cerrar accion="$set('avisoAbierto', false)">{{ __('Cancelar') }}</x-dosl.boton-cerrar>
            <button type="button" class="btn btn-guardar px-2" wire:click="guardarAviso"
                title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
        </x-slot>
    </x-dosl.dialog-modal>
</div>

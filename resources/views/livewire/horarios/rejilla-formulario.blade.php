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
                                                    {{ $asignatura->nombre }}{{ $asignatura->estado_id != \App\Models\Asignatura::ESTADO_ACTIVO ? ' ('.__('baja').')' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
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

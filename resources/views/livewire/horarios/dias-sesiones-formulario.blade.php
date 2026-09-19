<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="5xl">
    <x-slot name="title">
        {{ __('Días y sesiones') }}
    </x-slot>

    <x-slot name="content">
        <div class="mb-6">
            <x-label :value="__('Duración de la sesión (minutos)')" />
            <x-input id="ds-duracion" type="number" min="1" class="mt-1 w-40" wire:model="duracionSesionMinutos"
                autofocus />
            <x-input-error for="duracionSesionMinutos" class="mt-2" />
        </div>

        <div class="mb-6">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" wire:model="alinearHoras" />
                <span>{{ __('Ordenar por horas con hueco') }}</span>
            </label>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ __('Alinea la misma hora en los 7 días del horario semanal, dejando huecos (sin sesión) donde ese día no hay clase a esa hora.') }}
            </p>
        </div>

        <div class="mb-2 flex items-center justify-between">
            <x-label :value="__('Jornadas')" />
            <button type="button" class="btn btn-editar text-xs" wire:click="agregarJornada"
                title="{{ __('Añadir jornada') }}">
                <i class="fa-solid fa-plus"></i> {{ __('Añadir jornada') }}
            </button>
        </div>
        <x-input-error for="jornadas" class="mb-2" />

        @if (empty($jornadas))
            <div class="py-3 text-gray-500 dark:text-gray-400 text-sm">{{ __('Todavía no hay ninguna jornada.') }}</div>
        @endif

        <div class="space-y-4">
            @foreach ($jornadas as $indice => $jornada)
                <div class="border rounded-lg p-4 dark:border-gray-700" wire:key="jornada-{{ $indice }}">
                    <div class="flex items-start justify-between gap-4 mb-3">
                        <div class="flex-1">
                            <x-label :value="__('Nombre')" class="text-xs" />
                            <x-input type="text" class="w-full max-w-xs"
                                wire:model="jornadas.{{ $indice }}.nombre" placeholder="{{ __('P. ej. Mañana') }}" />
                            <x-input-error for="jornadas.{{ $indice }}.nombre" class="mt-1" />
                        </div>
                        <button type="button" class="text-red-600 hover:underline text-xs mt-5"
                            wire:click="quitarJornada({{ $indice }})" title="{{ __('Quitar jornada') }}">
                            <i class="fa-solid fa-trash"></i> {{ __('Quitar') }}
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-4 mb-3">
                        <div>
                            <x-label :value="__('Hora de inicio')" class="text-xs" />
                            <x-input type="time" class="w-36" wire:model="jornadas.{{ $indice }}.hora_inicio" />
                            <x-input-error for="jornadas.{{ $indice }}.hora_inicio" class="mt-1" />
                        </div>
                        <div>
                            <x-label :value="__('Número de sesiones')" class="text-xs" />
                            <x-input type="number" min="1" max="20" class="w-24"
                                wire:model="jornadas.{{ $indice }}.num_sesiones" />
                            <x-input-error for="jornadas.{{ $indice }}.num_sesiones" class="mt-1" />
                        </div>
                        <div>
                            <x-label :value="__('Recreo antes de la sesión nº')" class="text-xs" />
                            <x-input type="number" min="2" max="20" class="w-24" placeholder="{{ __('Sin recreo') }}"
                                wire:model="jornadas.{{ $indice }}.recreo_antes_de_sesion" />
                            <x-input-error for="jornadas.{{ $indice }}.recreo_antes_de_sesion" class="mt-1" />
                        </div>
                        <div>
                            <x-label :value="__('Duración del recreo (min)')" class="text-xs" />
                            <x-input type="number" min="1" class="w-24"
                                wire:model="jornadas.{{ $indice }}.recreo_duracion_minutos" />
                            <x-input-error for="jornadas.{{ $indice }}.recreo_duracion_minutos" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-label :value="__('Días en los que se da esta jornada')" class="text-xs" />
                        <div class="mt-1 flex flex-wrap gap-4">
                            @foreach ($diasSemana as $dia)
                                <label class="flex items-center gap-1 cursor-pointer">
                                    <input type="checkbox" wire:model="jornadas.{{ $indice }}.dias.{{ $dia->value }}" />
                                    <span>{{ $dia->nombre() }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

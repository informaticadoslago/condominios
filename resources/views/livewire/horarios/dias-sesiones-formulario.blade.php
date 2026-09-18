<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="4xl">
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
            <x-label :value="__('Días de clase')" />
            <div class="mt-2 flex flex-wrap gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" wire:model.live="tipoDias" value="lunes_viernes" />
                    <span>{{ __('Lunes a viernes') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" wire:model.live="tipoDias" value="lunes_domingo" />
                    <span>{{ __('Lunes a domingo') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" wire:model.live="tipoDias" value="personalizado" />
                    <span>{{ __('Personalizado') }}</span>
                </label>
            </div>
            <x-input-error for="tipoDias" class="mt-2" />

            @if ($tipoDias === 'personalizado')
                <div class="mt-3 flex flex-wrap gap-4">
                    @foreach ($diasSemana as $dia)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model.live="dias.{{ $dia->value }}.activo" />
                            <span>{{ $dia->nombre() }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <table class="table-striped w-full table-auto text-sm text-left">
                <thead class="font-medium border-b">
                    <tr>
                        <th class="py-3 px-4">{{ __('Día') }}</th>
                        <th class="py-3 px-4">{{ __('Hora primera sesión') }}</th>
                        <th class="py-3 px-4">{{ __('Número de sesiones') }}</th>
                        <th class="py-3 px-4">{{ __('Recreo antes de la sesión nº') }}</th>
                        <th class="py-3 px-4">{{ __('Duración del recreo (min)') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($diasSemana as $dia)
                        @if ($dias[$dia->value]['activo'])
                            <tr wire:key="dia-{{ $dia->value }}">
                                <td class="px-4 py-3">{{ $dia->nombre() }}</td>
                                <td class="px-4 py-3">
                                    <x-input type="time" class="w-36"
                                        wire:model="dias.{{ $dia->value }}.hora_primera_sesion" />
                                    <x-input-error for="dias.{{ $dia->value }}.hora_primera_sesion" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <x-input type="number" min="1" max="20" class="w-20"
                                        wire:model="dias.{{ $dia->value }}.num_sesiones" />
                                    <x-input-error for="dias.{{ $dia->value }}.num_sesiones" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <x-input type="number" min="2" max="20" class="w-20"
                                        placeholder="{{ __('Sin recreo') }}"
                                        wire:model="dias.{{ $dia->value }}.recreo_antes_de_sesion" />
                                    <x-input-error for="dias.{{ $dia->value }}.recreo_antes_de_sesion" class="mt-2" />
                                </td>
                                <td class="px-4 py-3">
                                    <x-input type="number" min="1" class="w-20"
                                        wire:model="dias.{{ $dia->value }}.recreo_duracion_minutos" />
                                    <x-input-error for="dias.{{ $dia->value }}.recreo_duracion_minutos" class="mt-2" />
                                </td>
                            </tr>
                        @endif
                    @endforeach
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

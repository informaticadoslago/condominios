<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="2xl">
    <x-slot name="title">
        {{ __('Nuevo examen') }}
    </x-slot>

    <x-slot name="content">
        <div>
            <x-label for="ex-asignatura" :value="__('Asignatura')" />
            <x-select id="ex-asignatura" class="block mt-1 w-full py-3" wire:model.live="asignatura_id">
                <option value="">{{ __('--') }}</option>
                @foreach ($asignaturas as $id => $nombre)
                    <option value="{{ $id }}">{{ $nombre }}</option>
                @endforeach
            </x-select>
            <x-input-error for="asignatura_id" class="mt-2" />
        </div>
        <div class="mt-4">
            {{-- Lo que se ve en el calendario del que se suscribe. Se propone al elegir la asignatura. --}}
            <x-label for="ex-titulo" :value="__('Texto del evento')" />
            <x-input id="ex-titulo" class="block mt-1 w-full" type="text" wire:model="titulo" maxlength="150" />
            <x-input-error for="titulo" class="mt-2" />
        </div>
        <div class="grid sm:grid-cols-3 gap-4 mt-4">
            <div>
                <x-label for="ex-fecha" :value="__('Fecha')" />
                <x-input id="ex-fecha" class="block mt-1 w-full" type="date" wire:model.live="fecha"
                    :min="today()->toDateString()" />
                <x-input-error for="fecha" class="mt-2" />
            </div>
            <div>
                {{-- Con la fecha se sabe el día de la semana, y con la rejilla las sesiones de la asignatura ese día. --}}
                <x-label for="ex-eleccion-hora" :value="__('Hora')" />
                <x-select id="ex-eleccion-hora" class="block mt-1 w-full py-3" wire:model.live="eleccionHora">
                    <option value="">{{ __('--') }}</option>
                    @foreach ($sesiones as $sesion)
                        <option value="{{ $sesion['hora'] }}">{{ __('Sesión :n', ['n' => $sesion['numero']]) }} · {{ $sesion['hora'] }}</option>
                    @endforeach
                    <option value="{{ \App\Livewire\Examenes\Formulario::HORA_PERSONALIZADA }}">{{ __('Hora personalizada') }}</option>
                </x-select>
                @if ($fecha && $asignatura_id && ! count($sesiones))
                    <p class="mt-1 text-sm text-gray-500">{{ __('Esa asignatura no tiene clase ese día.') }}</p>
                @endif
                @if ($eleccionHora === \App\Livewire\Examenes\Formulario::HORA_PERSONALIZADA)
                    <x-input id="ex-hora" class="block mt-2 w-full" type="time" wire:model.blur="hora" />
                @endif
                <x-input-error for="hora" class="mt-2" />
            </div>
            <div>
                {{-- Se propone una sesión de duración al elegir la hora; se puede cambiar. --}}
                <x-label for="ex-hora-fin" :value="__('Hora fin')" />
                <x-input id="ex-hora-fin" class="block mt-1 w-full" type="time" wire:model="hora_fin" />
                <x-input-error for="hora_fin" class="mt-2" />
            </div>
        </div>
        <div class="grid sm:grid-cols-3 gap-4 mt-4">
            <div>
                {{-- La alarma del evento en el calendario del que se suscribe. --}}
                <x-label for="ex-alarma" :value="__('Alarma')" />
                <x-select id="ex-alarma" class="block mt-1 w-full py-3" wire:model="alarma_minutos">
                    <option value="">{{ __('Sin alarma') }}</option>
                    @foreach (\App\Models\Examen::opcionesAlarma() as $minutos => $texto)
                        <option value="{{ $minutos }}">{{ $texto }}</option>
                    @endforeach
                </x-select>
                <x-input-error for="alarma_minutos" class="mt-2" />
            </div>
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

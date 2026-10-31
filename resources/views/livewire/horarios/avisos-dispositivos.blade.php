<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        {{ __('Configuración avisos') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('A qué dispositivos se envían los avisos de las sesiones de este horario') }}
    </x-slot>

    <x-slot name="content">
        <label class="flex items-center">
            <x-checkbox wire:model.live="todos" />
            <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Todos, también los que se añadan después') }}</span>
        </label>

        <div class="mt-3 ms-6 space-y-2">
            @foreach ($this->dispositivos as $dispositivo)
                <label class="flex items-center" wire:key="aviso-dispositivo-{{ $dispositivo->id }}">
                    <x-checkbox wire:model="marcados" value="{{ $dispositivo->id }}" :disabled="$todos" />
                    <span class="ms-2 text-sm {{ $todos ? 'text-gray-400 dark:text-gray-500' : 'text-gray-600 dark:text-gray-400' }}">
                        {{ $dispositivo->nombre ?: __('Dispositivo') }}
                    </span>
                </label>
            @endforeach
        </div>
        <x-input-error for="marcados" class="mt-2" />
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

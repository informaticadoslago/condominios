<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        {{ $itemId ? __('Modificar asignatura') : __('Nueva asignatura') }}
    </x-slot>

    <x-slot name="content">
        <div>
            <x-label for="a-nombre" :value="__('Nombre')" />
            <x-input id="a-nombre" class="block mt-1 w-full" type="text" wire:model="nombre" autofocus />
            <x-input-error for="nombre" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-label for="a-modulo" :value="__('Módulo')" />
            <x-select id="a-modulo" class="block mt-1 w-full py-3" wire:model="modulo_id">
                <option value="">{{ __('--') }}</option>
                @foreach ($modulos as $modulo)
                    <option value="{{ $modulo->id }}">
                        {{ $modulo->nombre }}{{ $modulo->estado_id != \App\Models\Modulo::ESTADO_ACTIVO ? ' ('.__('baja').')' : '' }}
                    </option>
                @endforeach
            </x-select>
            <x-input-error for="modulo_id" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-label for="a-aula" :value="__('Aula')" />
            <x-input id="a-aula" class="block mt-1 w-full uppercase" type="text" wire:model="aula" maxlength="10" placeholder="INF3" />
            <x-input-error for="aula" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

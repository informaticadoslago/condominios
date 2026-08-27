<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        {{ $itemId ? __('Modificar actividad') : __('Nueva actividad') }}
    </x-slot>

    <x-slot name="content">
        <div>
            <x-label for="act-nombre" :value="__('Nombre')" />
            <x-input id="act-nombre" class="block mt-1 w-full" type="text" wire:model="nombre" />
            <x-input-error for="nombre" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        {{ $itemId ? __('Modificar horario') : __('Nuevo horario') }}
    </x-slot>

    <x-slot name="content">
        <div>
            <x-label for="h-nombre" :value="__('Nombre')" />
            <x-input id="h-nombre" class="block mt-1 w-full" type="text" wire:model="nombre" autofocus />
            <x-input-error for="nombre" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

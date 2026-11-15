<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        {{ $itemId ? __('Modificar evaluación') : __('Nueva evaluación') }}
    </x-slot>

    <x-slot name="content">
        <div>
            <x-label for="ev-nombre" :value="__('Nombre')" />
            <x-input id="ev-nombre" class="block mt-1 w-full" type="text" wire:model="nombre" maxlength="100" autofocus />
            <x-input-error for="nombre" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-label for="ev-fecha-inicio" :value="__('Fecha de inicio')" />
            <x-input id="ev-fecha-inicio" class="block mt-1 w-full" type="date" wire:model="fecha_inicio" />
            <x-input-error for="fecha_inicio" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-label for="ev-fecha-fin" :value="__('Fecha de fin')" />
            <x-input id="ev-fecha-fin" class="block mt-1 w-full" type="date" wire:model="fecha_fin" />
            <x-input-error for="fecha_fin" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        {{ __('Nuevo ejercicio contable') }}
    </x-slot>

    <x-slot name="content">
        <div>
            <x-label for="ec-nombre" :value="__('Nombre')" />
            <x-input id="ec-nombre" class="block mt-1 w-full" type="text" wire:model="nombre" />
            <x-input-error for="nombre" class="mt-2" />
        </div>
        <div class="flex w-full mt-3">
            <div class="w-1/2 mr-4">
                <x-label for="ec-fecha-inicio" :value="__('Fecha inicio')" />
                <x-input id="ec-fecha-inicio" class="block mt-1 w-full" type="date" wire:model.live="fecha_inicio" />
                <x-input-error for="fecha_inicio" class="mt-2" />
            </div>
            <div class="w-1/2">
                <x-label for="ec-fecha-fin" :value="__('Fecha fin')" />
                <x-input id="ec-fecha-fin" class="block mt-1 w-full" type="date" wire:model="fecha_fin" />
                <x-input-error for="fecha_fin" class="mt-2" />
            </div>
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

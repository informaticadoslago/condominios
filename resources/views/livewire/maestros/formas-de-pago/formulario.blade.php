<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        {{ $itemId ? __('Modificar forma de pago') : __('Nueva forma de pago') }}
    </x-slot>

    <x-slot name="content">
        <div>
            <x-label for="fp-descripcion" :value="__('Descripción')" />
            <x-input id="fp-descripcion" class="block mt-1 w-full" type="text" wire:model="descripcion" autofocus />
            <x-input-error for="descripcion" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

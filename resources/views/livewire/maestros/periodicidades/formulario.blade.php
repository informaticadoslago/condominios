<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        {{ $itemId ? __('Modificar periodicidad') : __('Nueva periodicidad') }}
    </x-slot>

    <x-slot name="content">
        <div>
            <x-label for="pp-descripcion" :value="__('Descripción')" />
            <x-input id="pp-descripcion" class="block mt-1 w-full" type="text" wire:model="descripcion" autofocus />
            <x-input-error for="descripcion" class="mt-2" />
        </div>
        <div class="mt-3">
            <x-label for="pp-meses" :value="__('Meses')" />
            <x-input id="pp-meses" class="block mt-1 w-full" type="number" min="1" wire:model="meses" />
            <x-input-error for="meses" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

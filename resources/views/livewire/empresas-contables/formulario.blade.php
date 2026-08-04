<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        {{ $itemId ? __('Modificar empresa contable') : __('Nueva empresa contable') }}
    </x-slot>

    <x-slot name="content">
        <div class="mt-1">
            <x-label for="ec-razon-social" :value="__('Razón social')" />
            <x-input id="ec-razon-social" class="block mt-1 w-full mayusculas" type="text"
                wire:model="razon_social" forzar-may autofocus />
            <x-input-error for="razon_social" class="mt-2" />
        </div>
        <div class="mt-3">
            <x-label for="ec-cif" :value="__('CIF')" />
            <x-input id="ec-cif" class="block mt-1 w-full mayusculas" type="text" wire:model="cif"
                forzar-may />
            <x-input-error for="cif" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

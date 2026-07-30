<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        {{ $itemId ? __('Modificar entidad financiera') : __('Nueva entidad financiera') }}
    </x-slot>

    <x-slot name="content">
        <div class="flex w-full">
            <div class="w-1/3 mr-4">
                <x-label for="eb-codigo" :value="__('Código')" />
                <x-input id="eb-codigo" class="block mt-1 w-full" type="text" wire:model="codigo" autofocus />
                <x-input-error for="codigo" class="mt-2" />
            </div>
            <div class="w-2/3">
                <x-label for="eb-bic" :value="__('BIC')" />
                <x-input id="eb-bic" class="block mt-1 w-full mayusculas" type="text" wire:model="bic" forzar-may />
                <x-input-error for="bic" class="mt-2" />
            </div>
        </div>
        <div class="mt-3">
            <x-label for="eb-descripcion" :value="__('Descripción')" />
            <x-input id="eb-descripcion" class="block mt-1 w-full mayusculas" type="text" wire:model="descripcion" forzar-may />
            <x-input-error for="descripcion" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

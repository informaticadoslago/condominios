<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        {{ $formulario->comunidad?->exists ? __('Modificar comunidad') : __('Nueva comunidad') }}
    </x-slot>

    <x-slot name="content">
        <div class="mt-1">
            <x-label for="com-nombre" :value="__('Nombre')" />
            <x-input id="com-nombre" class="block mt-1 w-full mayusculas" type="text" wire:model="formulario.nombre"
                forzar-may autofocus />
            <x-input-error for="formulario.nombre" class="mt-2" />
        </div>
        <div class="mt-3">
            <x-label for="com-cif" :value="__('CIF')" />
            <x-input id="com-cif" class="block mt-1 w-full mayusculas" type="text" wire:model="formulario.cif"
                forzar-may />
            <x-input-error for="formulario.cif" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

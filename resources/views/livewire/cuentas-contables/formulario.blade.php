<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        {{ $itemId ? __('Modificar cuenta contable') : __('Nueva cuenta contable') }}
    </x-slot>

    <x-slot name="content">
        <div class="flex w-full">
            <div class="w-1/3 mr-4">
                <x-label for="cc-codigo" :value="__('Código')" />
                <x-input id="cc-codigo" class="block mt-1 w-full" type="text" wire:model="codigo" autofocus />
                <x-input-error for="codigo" class="mt-2" />
            </div>
            <div class="w-2/3">
                <x-label for="cc-tipo" :value="__('Tipo')" />
                <x-select id="cc-tipo" class="block mt-1 w-full" wire:model="tipo_cuenta_contable_id">
                    <option value="">{{ __('--') }}</option>
                    @foreach ($tipos as $tipo)
                        <option value="{{ $tipo->id }}">{{ $tipo->descripcion }}</option>
                    @endforeach
                </x-select>
                <x-input-error for="tipo_cuenta_contable_id" class="mt-2" />
            </div>
        </div>
        <div class="mt-3">
            <x-label for="cc-nombre" :value="__('Nombre')" />
            <x-input id="cc-nombre" class="block mt-1 w-full mayusculas" type="text" wire:model="nombre" forzar-may />
            <x-input-error for="nombre" class="mt-2" />
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

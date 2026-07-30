<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        {{ $itemId ? __('Modificar país') : __('Nuevo país') }}
    </x-slot>

    <x-slot name="content">
        <div class="space-y-3">
            <div>
                <x-label for="pais-nombre" :value="__('Nombre')" />
                <x-input id="pais-nombre" class="block mt-1 w-full mayusculas" type="text" wire:model="nombre" autofocus />
                <x-input-error for="nombre" class="mt-2" />
            </div>

            <div class="flex gap-2">
                <div class="w-1/2">
                    <x-label for="pais-codigo1" :value="__('Código ISO (2)')" />
                    <x-input id="pais-codigo1" class="block mt-1 w-full mayusculas" type="text" wire:model="codigo1" />
                    <x-input-error for="codigo1" class="mt-2" />
                </div>
                <div class="w-1/2">
                    <x-label for="pais-codigo2" :value="__('Código ISO (3)')" />
                    <x-input id="pais-codigo2" class="block mt-1 w-full mayusculas" type="text" wire:model="codigo2" />
                    <x-input-error for="codigo2" class="mt-2" />
                </div>
            </div>

            <div class="flex gap-2">
                <div class="w-1/2">
                    <x-label for="pais-grupo" :value="__('Grupo')" />
                    <x-select id="pais-grupo" class="block mt-1 w-full py-3" wire:model="grupo">
                        <option value="{{ \App\Models\Pais::GRUPO_UE }}">{{ __('UE') }}</option>
                        <option value="{{ \App\Models\Pais::GRUPO_OTRO }}">{{ __('Resto') }}</option>
                    </x-select>
                    <x-input-error for="grupo" class="mt-2" />
                </div>
                <div class="w-1/2">
                    <x-label for="pais-orden" :value="__('Orden')" />
                    <x-input id="pais-orden" class="block mt-1 w-full" type="number" min="0" max="127" wire:model="orden" />
                    <x-input-error for="orden" class="mt-2" />
                </div>
            </div>
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

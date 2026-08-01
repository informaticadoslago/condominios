<div class="flex flex-col {{ $embebido ? 'min-h-[28rem]' : 'min-h-[calc(100vh-12rem)]' }}">
    @include('livewire.propietarios.crear.navigation')

    <div class="flex-1 space-y-4">
        <p class="text-sm text-gray-500">{{ __('Opcional: se puede rellenar más adelante desde la ficha del propietario.') }}</p>

        <div class="flex w-full">
            <div class="mt-2 w-2/5">
                <x-label :value="__('Código postal')" />
                <x-input class="block mt-1 w-full" type="text" wire:model.live="codigo_postal" />
                <x-input-error for="codigo_postal" class="mt-2" />
            </div>
            <div class="mt-2 ml-2 w-3/5">
                <x-label :value="__('Provincia')" />
                <x-select class="block mt-1 w-full mayusculas" wire:model.live="provincia_id">
                    <option value="">{{ __('--') }}</option>
                    @foreach ($provincias as $provincia)
                        <option value="{{ $provincia->id }}">{{ $provincia->nombre }}</option>
                    @endforeach
                </x-select>
                <x-input-error for="provincia_id" class="mt-2" />
            </div>
        </div>

        <div>
            <x-label :value="__('Municipio')" />
            <x-select class="block mt-1 w-full mayusculas" wire:model="municipio_id" :disabled="! $provincia_id">
                <option value="">{{ __('--') }}</option>
                @foreach ($municipios as $municipio)
                    <option value="{{ $municipio->id }}">{{ $municipio->nombre }}</option>
                @endforeach
            </x-select>
            <x-input-error for="municipio_id" class="mt-2" />
        </div>

        <div>
            <x-label :value="__('Dirección')" />
            <x-input class="block mt-1 w-full" type="text" wire:model="direccion1" placeholder="{{ __('Calle, avenida...') }}" />
            <x-input-error for="direccion1" class="mt-2" />
        </div>

        <div class="flex w-full">
            <div class="mt-2 mr-4 w-1/3">
                <x-label :value="__('Número')" />
                <x-input class="block mt-1 w-full" type="text" wire:model="numero" />
                <x-input-error for="numero" class="mt-2" />
            </div>
            <div class="mt-2 mr-4 w-1/3">
                <x-label :value="__('Piso')" />
                <x-input class="block mt-1 w-full" type="text" wire:model="piso" />
                <x-input-error for="piso" class="mt-2" />
            </div>
            <div class="mt-2 w-1/3">
                <x-label :value="__('Puerta')" />
                <x-input class="block mt-1 w-full" type="text" wire:model="puerta" />
                <x-input-error for="puerta" class="mt-2" />
            </div>
        </div>
    </div>

    <div class="flex items-center justify-between border-t pt-4 mt-4">
        <div>
            @if ($this->hasPreviousStep())
                <x-button type="button" wire:click="stepBack" class="bg-gray-500 hover:bg-gray-600 text-white">
                    {{ __('Anterior') }}
                </x-button>
            @endif
        </div>
        <div class="flex gap-2">
            <span x-data="{ shift: false }" @keydown.shift.window="shift = true" @keyup.shift.window="shift = false" x-on:blur.window="shift = false">
                <x-button type="button" tabindex="-1" wire:click="salir($event.shiftKey)"
                    x-bind:class="shift ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-500 hover:bg-gray-600'" class="text-white">
                    <i class="fa-solid" x-bind:class="shift ? 'fa-trash' : 'fa-arrow-right-from-bracket'"></i>
                    <span x-show="!shift">{{ __('Salir') }}</span>
                    <span x-show="shift" x-cloak>{{ __('Salir y eliminar borrador') }}</span>
                </x-button>
            </span>
            <x-button type="button" wire:click="submit">{{ __('Siguiente') }}</x-button>
        </div>
    </div>
</div>

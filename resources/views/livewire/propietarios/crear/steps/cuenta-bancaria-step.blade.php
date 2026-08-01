<div class="flex flex-col {{ $embebido ? 'min-h-[28rem]' : 'min-h-[calc(100vh-12rem)]' }}">
    @include('livewire.propietarios.crear.navigation')

    <div class="flex-1 space-y-4">
        <p class="text-sm text-gray-500">{{ __('Opcional: se puede rellenar más adelante desde la ficha del propietario.') }}</p>

        <div>
            <x-label :value="__('IBAN')" />
            <x-input class="block mt-1 w-full mayusculas" type="text" wire:model="iban" autofocus />
            <x-input-error for="iban" class="mt-2" />
        </div>

        <div class="flex w-full">
            <div class="mt-2 mr-4 w-1/2">
                <x-label :value="__('Entidad bancaria')" />
                <div class="mt-1">
                    <x-dosl.input-autocomplete
                        wire:model="entidad_bancaria_texto"
                        source="buscarEntidadesBancarias"
                        items="resultadosEntidadesBancarias"
                        valorCampo="valor"
                        etiquetaCampo="etiqueta"
                        valorModel="entidad_bancaria_id"
                        placeholder="{{ __('Código o nombre...') }}" />
                </div>
                <x-input-error for="entidad_bancaria_id" class="mt-2" />
            </div>
            <div class="mt-2 w-1/2">
                <x-label :value="__('Alias')" />
                <x-input class="block mt-1 w-full" type="text" wire:model="alias" placeholder="{{ __('Cuenta principal...') }}" />
                <x-input-error for="alias" class="mt-2" />
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
            <x-button type="button" wire:click="terminar">{{ __('Terminar') }}</x-button>
        </div>
    </div>
</div>

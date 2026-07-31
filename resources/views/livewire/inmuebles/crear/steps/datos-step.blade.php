<div class="flex flex-col min-h-[calc(100vh-12rem)]">
    @include('livewire.inmuebles.crear.navigation')

    <div class="flex-1 space-y-4">
        <div class="flex w-full">
            <div class="mt-2 w-2/5">
                <x-label for="select-inmueble-comunidad" :value="__('Comunidad')" />
                <x-select id="select-inmueble-comunidad" class="block mt-1 w-full mayusculas" name="comunidad"
                    wire:model="comunidad_id">
                    <option value="">{{ __('--') }}</option>
                    @foreach ($comunidades as $comunidad)
                        <option value="{{ $comunidad->id }}">{{ $comunidad->nombre }}</option>
                    @endforeach
                </x-select>
                <x-input-error for="comunidad_id" class="mt-2" />
            </div>
            <div class="mt-2 ml-2 w-[30%]">
                <x-label for="select-inmueble-tipo" :value="__('Tipo de inmueble')" />
                <x-select id="select-inmueble-tipo" class="block mt-1 w-full mayusculas" name="tipo_inmueble"
                    wire:model="tipo_inmueble_id">
                    <option value="">{{ __('--') }}</option>
                    @foreach ($tiposInmueble as $tipo)
                        <option value="{{ $tipo->id }}">{{ $tipo->descripcion }}</option>
                    @endforeach
                </x-select>
                <x-input-error for="tipo_inmueble_id" class="mt-2" />
            </div>
            <div class="mt-2 ml-2 w-[30%]">
                <x-label for="select-inmueble-ocupacion" :value="__('Ocupación')" />
                <x-select id="select-inmueble-ocupacion" class="block mt-1 w-full mayusculas" name="ocupacion"
                    wire:model="ocupacion_id">
                    <option value="">{{ __('--') }}</option>
                    @foreach ($ocupaciones as $ocupacion)
                        <option value="{{ $ocupacion->id }}">{{ $ocupacion->descripcion }}</option>
                    @endforeach
                </x-select>
                <x-input-error for="ocupacion_id" class="mt-2" />
            </div>
        </div>

        <div class="flex w-full mt-3">
            <div class="w-1/5">
                <x-label for="input-inmueble-planta" :value="__('Planta')" />
                <x-input id="input-inmueble-planta" class="block mt-1 w-full" type="number" name="planta"
                    wire:model="planta" />
                <x-input-error for="planta" class="mt-2" />
            </div>
            <div class="ml-2 w-1/5">
                <x-label for="input-inmueble-puerta" :value="__('Puerta')" />
                <x-input id="input-inmueble-puerta" class="block mt-1 w-full mayusculas" type="text" name="puerta"
                    wire:model="puerta" forzar-may />
                <x-input-error for="puerta" class="mt-2" />
            </div>
            <div class="ml-2 w-1/5">
                <x-label for="input-inmueble-coeficiente" :value="__('Coeficiente %')" />
                <x-input id="input-inmueble-coeficiente" class="block mt-1 w-full" type="text" name="coeficiente"
                    wire:model="coeficiente" />
                <x-input-error for="coeficiente" class="mt-2" />
            </div>
            <div class="ml-2 w-2/5">
                <x-label for="input-inmueble-referencia" :value="__('Referencia catastral')" />
                <x-input id="input-inmueble-referencia" class="block mt-1 w-full mayusculas" type="text"
                    name="referencia_catastral" wire:model="referencia_catastral" forzar-may />
                <x-input-error for="referencia_catastral" class="mt-2" />
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
            <x-button type="button" tabindex="-1" wire:click="salir" class="bg-gray-500 hover:bg-gray-600 text-white">{{ __('Salir') }}</x-button>
            <x-button type="button" wire:click="submit">{{ __('Siguiente') }}</x-button>
        </div>
    </div>
</div>

<div class="flex flex-col {{ $embebido ? 'min-h-[28rem]' : 'min-h-[calc(100vh-12rem)]' }}">
    @include('livewire.propietarios.crear.navigation')

    <div class="flex-1 space-y-4">
        <p class="text-sm text-gray-500">{{ __('Opcional: se puede rellenar más adelante desde la ficha del propietario.') }}</p>

        <div class="flex w-full">
            <div class="mt-2 mr-4 w-1/2">
                <x-label :value="__('Teléfono')" />
                <x-input class="block mt-1 w-full" type="text" wire:model="telefono" autofocus />
                <x-input-error for="telefono" class="mt-2" />
            </div>
            <div class="mt-2 w-1/2">
                <x-label :value="__('Email')" />
                <x-input class="block mt-1 w-full" type="email" wire:model.blur="email" />
                <x-input-error for="email" class="mt-2" />

                {{-- Estado de la dirección ya guardada. Mientras no se confirme no se le
                     pueden mandar recibos ni avisos con garantía de que llegan. --}}
                @if ($contactoCorreo)
                    <p class="mt-2 text-sm">
                        @if ($contactoCorreo->estaValidado())
                            <i class="fa-solid fa-circle-check text-green-600"></i>
                            <span class="text-gray-600 dark:text-gray-400">
                                {{ __('Validado el :fecha', ['fecha' => $contactoCorreo->verified_at->format('d-m-Y')]) }}
                            </span>
                        @else
                            <i class="fa-solid fa-circle-exclamation text-amber-500"></i>
                            <span class="text-gray-600 dark:text-gray-400">{{ __('Sin validar') }}</span>
                        @endif
                    </p>

                    @if ($puedeVerificar)
                        <button type="button" wire:click="enviarVerificacion"
                            class="mt-1 text-sm text-indigo-600 hover:underline">
                            <i class="fa-solid fa-envelope"></i> {{ __('Enviar correo de verificación') }}
                        </button>
                    @endif
                @endif
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

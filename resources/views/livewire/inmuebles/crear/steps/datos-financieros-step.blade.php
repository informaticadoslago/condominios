<div class="flex flex-col min-h-[calc(100vh-12rem)]">
    @include('livewire.inmuebles.crear.navigation')

    <div class="flex-1 space-y-4">
        <div class="w-1/3">
            <x-label for="select-inmueble-forma-pago" :value="__('Forma de pago')" />
            <x-select id="select-inmueble-forma-pago" class="block mt-1 w-full mayusculas" wire:model.live="forma_de_pago_id">
                <option value="">{{ __('--') }}</option>
                @foreach ($formasDePago as $forma)
                    <option value="{{ $forma->id }}">{{ $forma->descripcion }}</option>
                @endforeach
            </x-select>
            <x-input-error for="forma_de_pago_id" class="mt-2" />
        </div>

        <div class="flex w-full">
            {{-- El responsable del pago se pide siempre, haya domiciliación o no: es a
                 quien se le emite el recibo y a quien se le avisa. Ordenados de mayor a
                 menor cuota. --}}
            <div class="w-1/2">
                <x-label for="select-inmueble-titular-pago" :value="__('Propietario responsable del pago')" />
                <x-select id="select-inmueble-titular-pago" class="block mt-1 w-full mayusculas" wire:model.live="persona_comunidad_id_pago">
                    <option value="">{{ __('--') }}</option>
                    @foreach ($titulares as $titular)
                        <option value="{{ $titular['persona_comunidad_id'] }}">{{ $titular['nombre'] }}</option>
                    @endforeach
                </x-select>
                <x-input-error for="persona_comunidad_id_pago" class="mt-2" />
            </div>

            @if ($esReciboBancario)
                <div class="ml-2 w-1/2">
                    <x-label for="select-inmueble-cuenta-pago" :value="__('Cuenta bancaria')" />
                    <x-select id="select-inmueble-cuenta-pago" class="block mt-1 w-full" wire:model="cuenta_bancaria_id">
                        <option value="">{{ __('--') }}</option>
                        @foreach ($cuentas as $cuenta)
                            <option value="{{ $cuenta['id'] }}">{{ $cuenta['texto'] }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error for="cuenta_bancaria_id" class="mt-2" />
                    @if ($persona_comunidad_id_pago && ! count($cuentas))
                        <p class="mt-1 text-sm text-amber-600">{{ __('Este propietario no tiene ninguna cuenta bancaria registrada.') }}</p>
                        <button type="button" wire:click="abrirModalPropietario" class="mt-1 text-sm text-indigo-600 hover:underline">
                            <i class="fa-solid fa-pen"></i> {{ __('Editar propietario para añadirle una cuenta bancaria') }}
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Wizard completo de Propietario embebido: al terminar (o salir), dispara un
         evento (ver propietarioActualizado()/cerrarModalPropietario()) en vez de
         navegar a otra página. Con propietarioIdParaModal se edita al propietario ya
         existente; sin él, se da de alta (la persona ya existe, se detecta por
         documento y solo hace falta confirmarla y añadirle la cuenta). --}}
    <x-dosl.dialog-modal wire:model.live="modalPropietarioAbierto" class="backdrop-blur" maxWidth="7xl">
        <x-slot name="title">
            {{ __('Propietario') }}
        </x-slot>
        <x-slot name="content">
            @if ($modalPropietarioAbierto)
                @livewire('propietarios.crear.crear-propietario', [
                    'propietarioId' => $propietarioIdParaModal,
                    'comunidadId'   => $comunidadIdParaModal,
                    'embebido'      => true,
                ], key('propietario-embebido-financiero-'.$modalPropietarioContador))
            @endif
        </x-slot>
    </x-dosl.dialog-modal>

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

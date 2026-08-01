<div class="flex flex-col min-h-[calc(100vh-12rem)]">
    @include('livewire.inmuebles.crear.navigation')

    <div class="flex-1 space-y-4">
        {{-- Lista de propietarios ya asignados --}}
        <div>
            @php($sumaCuotas = collect($propietarios)->sum('cuota_percent'))
            <div class="flex items-center justify-between">
                <x-label :value="__('Propietarios')" />
                <span class="text-sm {{ abs($sumaCuotas - 100) > 0.01 ? 'text-red-600' : 'text-green-600' }}">
                    {{ __('Suma de cuotas') }}: {{ number_format($sumaCuotas, 2) }}%
                </span>
            </div>
            @if (count($propietarios))
                <ul class="border rounded mt-1 divide-y">
                    @foreach ($propietarios as $propietario)
                        <li class="px-3 py-2">
                            @if ($editandoId === $propietario['ref'])
                                <div class="flex items-end gap-2">
                                    <div class="flex-1">
                                        <span class="mayusculas">{{ $propietario['nombre'] }}</span>
                                    </div>
                                    <div class="w-1/6">
                                        <x-label :value="__('Cuota %')" />
                                        <x-input type="text" class="block mt-1 w-full" wire:model="edit_cuota_percent" />
                                    </div>
                                    <div class="w-1/4">
                                        <x-label :value="__('Causa')" />
                                        <x-select class="block mt-1 w-full py-3" wire:model="edit_causa">
                                            @foreach ($causas as $valor => $etiqueta)
                                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                            @endforeach
                                        </x-select>
                                    </div>
                                    <div class="flex gap-1">
                                        <x-button type="button" wire:click="guardarEdicion" class="btn">{{ __('Guardar') }}</x-button>
                                        <button type="button" wire:click="cancelarEdicion" class="text-sm text-gray-500 hover:text-gray-800 px-2">{{ __('Cancelar') }}</button>
                                    </div>
                                </div>
                                <x-input-error for="edit_cuota_percent" class="mt-2" />
                                <x-input-error for="edit_causa" class="mt-2" />
                            @else
                                <div class="flex items-center gap-2">
                                    <span class="mayusculas flex-1">{{ $propietario['nombre'] }}</span>
                                    <span class="text-sm text-gray-500">{{ number_format($propietario['cuota_percent'], 2) }}%</span>
                                    <span class="text-sm text-gray-500">{{ $propietario['causa'] }}</span>
                                    <button type="button" wire:click="activarEdicion({{ $propietario['ref'] }})"
                                        class="text-gray-500 hover:text-indigo-600" title="{{ __('Editar') }}">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button type="button" wire:click="quitarPropietario({{ $propietario['ref'] }})"
                                        class="text-gray-500 hover:text-red-600" title="{{ __('Quitar') }}">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-1 text-sm text-gray-500">{{ __('Sin propietarios todavía.') }}</p>
            @endif
            <x-input-error for="persona_id" class="mt-2" />
        </div>

        {{-- Alta de un propietario --}}
        <div class="border rounded p-3 space-y-3">
            <p class="text-sm font-semibold text-gray-600 dark:text-gray-300">{{ __('Añadir propietario') }}</p>

            @if ($persona_id)
                <div class="flex items-center gap-2">
                    <span class="mayusculas flex-1">{{ $personaNombre }}</span>
                    <button type="button" wire:click="quitarSeleccion" class="text-gray-500 hover:text-gray-800" title="{{ __('Quitar') }}">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            @else
                <x-input type="text" class="block w-full" placeholder="{{ __('Buscar propietario por nombre o NIF…') }}" wire:model.live="personaBusqueda" />
                @if (! empty($personaResultados))
                    <ul class="border rounded mt-1 divide-y max-h-48 overflow-y-auto">
                        @foreach ($personaResultados as $resultado)
                            <li>
                                <button type="button" wire:click="seleccionarPersona({{ $resultado['id'] }})"
                                    class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 mayusculas">
                                    {{ $resultado['texto'] }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <div class="mt-1">
                    <button type="button" wire:click="abrirModalPropietario" class="text-sm text-indigo-600 hover:underline">
                        <i class="fa-solid fa-plus"></i> {{ __('No existe: dar de alta un propietario nuevo') }}
                    </button>
                </div>
            @endif

            <div class="flex items-end gap-2">
                <div class="w-1/6">
                    <x-label :value="__('Cuota %')" />
                    <x-input type="text" class="block mt-1 w-full" wire:model="cuota_percent" placeholder="0,00-100" />
                    <x-input-error for="cuota_percent" class="mt-2" />
                </div>
                <div class="w-1/4">
                    <x-label :value="__('Causa')" />
                    <x-select class="block mt-1 w-full py-3" wire:model="causa">
                        @foreach ($causas as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="flex-1 text-right">
                    <x-button type="button" wire:click="agregarPropietario" class="btn">
                        <i class="fa-solid fa-plus"></i> {{ __('Añadir') }}
                    </x-button>
                </div>
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

    {{-- Wizard completo de Propietario embebido: al terminar (o salir), dispara un
         evento (ver PropietariosStep::propietarioCreado/cerrarModalPropietario) en vez
         de navegar a otra página. --}}
    <x-dosl.dialog-modal wire:model.live="modalPropietarioAbierto" class="backdrop-blur" maxWidth="7xl">
        <x-slot name="title">
            {{ __('Nuevo propietario') }}
        </x-slot>
        <x-slot name="content">
            @if ($modalPropietarioAbierto)
                @livewire('propietarios.crear.crear-propietario', ['embebido' => true], key('propietario-embebido-'.$modalPropietarioContador))
            @endif
        </x-slot>
    </x-dosl.dialog-modal>
</div>

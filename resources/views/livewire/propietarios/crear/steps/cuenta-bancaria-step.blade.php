<div class="flex flex-col {{ $embebido ? 'min-h-[28rem]' : 'min-h-[calc(100vh-12rem)]' }}">
    @include('livewire.propietarios.crear.navigation')

    <div class="flex-1 space-y-4">
        <p class="text-sm text-gray-500">{{ __('Opcional: se puede rellenar más adelante desde la ficha del propietario.') }}</p>

        @if ($cuentasExistentes->count())
            <table class="w-full text-sm text-left">
                <thead>
                    <tr class="border-b">
                        <th class="py-1 pr-4">{{ __('IBAN') }}</th>
                        <th class="py-1 pr-4">{{ __('Entidad') }}</th>
                        <th class="py-1 pr-4">{{ __('Alias') }}</th>
                        <th class="py-1 pr-4">{{ __('Estado') }}</th>
                        <th class="py-1"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($cuentasExistentes as $cuenta)
                        <tr wire:key="cuenta-existente-{{ $cuenta->id }}">
                            <td class="py-2 pr-4 mayusculas">{{ $cuenta->iban }}</td>
                            <td class="py-2 pr-4">{{ $cuenta->entidadBancaria?->descripcion }}</td>
                            <td class="py-2 pr-4">{{ $cuenta->alias }}</td>
                            <td class="py-2 pr-4">
                                @if ($cuenta->estado_id == \App\Models\CuentaBancaria::ESTADO_ACTIVA)
                                    <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">
                                        {{ __('Activa') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        {{ __('Cancelada') }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-2 text-right">
                                @if ($cuenta->estado_id == \App\Models\CuentaBancaria::ESTADO_ACTIVA)
                                    <x-button type="button" class="bg-red-600 hover:bg-red-700 text-white"
                                        wire:click="confirmarCancelarCuenta({{ $cuenta->id }})" title="{{ __('Cancelar') }}">
                                        <i class="fa-solid fa-ban"></i>
                                    </x-button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if (count($cuentasPendientes))
            <table class="w-full text-sm text-left">
                <thead>
                    <tr class="border-b">
                        <th class="py-1 pr-4">{{ __('IBAN') }}</th>
                        <th class="py-1 pr-4">{{ __('Entidad') }}</th>
                        <th class="py-1 pr-4">{{ __('Alias') }}</th>
                        <th class="py-1"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($cuentasPendientes as $pendiente)
                        <tr wire:key="cuenta-pendiente-{{ $pendiente['_key'] }}">
                            <td class="py-2 pr-4 mayusculas">{{ $pendiente['iban'] }}</td>
                            <td class="py-2 pr-4">{{ $pendiente['entidad_bancaria_texto'] }}</td>
                            <td class="py-2 pr-4">{{ $pendiente['alias'] }}</td>
                            <td class="py-2 text-right">
                                <button type="button" class="text-gray-400 hover:text-gray-800" title="{{ __('Quitar') }}"
                                    wire:click="quitarCuentaPendiente('{{ $pendiente['_key'] }}')">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div>
            <x-label :value="__('Añadir cuenta bancaria')" />
            <div class="flex items-end gap-4 mt-1">
                <div class="flex flex-1 gap-4">
                    <div class="w-3/6">
                        <x-input class="mayusculas w-full" type="text" wire:model="nueva.iban" placeholder="{{ __('IBAN') }}" />
                        <x-input-error for="nueva.iban" class="mt-2" />
                    </div>
                    <div class="w-2/6">
                        <x-dosl.input-autocomplete
                            wire:model="nueva.entidad_bancaria_texto"
                            source="buscarEntidadesBancarias"
                            items="resultadosEntidadesBancarias"
                            valorCampo="valor"
                            etiquetaCampo="etiqueta"
                            valorModel="nueva.entidad_bancaria_id"
                            placeholder="{{ __('Entidad: código o nombre...') }}" />
                        <x-input-error for="nueva.entidad_bancaria_id" class="mt-2" />
                    </div>
                    <div class="w-1/6">
                        <x-input class="w-full" type="text" wire:model="nueva.alias" placeholder="{{ __('Alias') }}" />
                    </div>
                </div>
                <x-button type="button" wire:click="agregarCuenta" title="{{ __('Añadir cuenta') }}">
                    <i class="fa-solid fa-plus"></i>
                </x-button>
            </div>
        </div>

        @if ($propietarioEsMenor && ! empty($nueva['iban']))
            <div class="border rounded p-3 space-y-3">
                <p class="text-sm font-semibold text-gray-600 dark:text-gray-300">
                    {{ __('El propietario es menor de edad: la cuenta tiene que tener como titular a una persona mayor de edad.') }}
                </p>

                @if ($titularNuevo)
                    <div class="flex w-full">
                        <div class="mt-2 w-1/4">
                            <x-label :value="__('País')" />
                            <x-select class="block mt-1 w-full mayusculas" wire:model="titular_documento_pais_id">
                                @foreach ($paises as $pais)
                                    <option value="{{ $pais->id }}">{{ $pais->nombre }}</option>
                                @endforeach
                            </x-select>
                        </div>
                        <div class="mt-2 ml-2 w-1/5">
                            <x-label :value="__('Tipo documento')" />
                            <x-select class="block mt-1 w-full mayusculas" wire:model="titular_tipo_documento_id">
                                @foreach ($tiposDocumentoTitular as $tipo)
                                    <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                                @endforeach
                            </x-select>
                        </div>
                        <div class="mt-2 ml-2 w-[55%]">
                            <x-label :value="__('Documento Id.')" />
                            <x-input class="block mt-1 w-full mayusculas" type="text" wire:model="titular_documento_identificativo" />
                            <x-input-error for="titular_documento_identificativo" class="mt-2" />
                        </div>
                    </div>
                    <div class="flex w-full">
                        <div class="mt-2 mr-4 w-1/3">
                            <x-label :value="__('Nombre')" />
                            <x-input class="block mt-1 w-full" type="text" wire:model="titular_nombre" />
                            <x-input-error for="titular_nombre" class="mt-2" />
                        </div>
                        <div class="mt-2 mr-4 w-1/3">
                            <x-label :value="__('Apellido 1')" />
                            <x-input class="block mt-1 w-full" type="text" wire:model="titular_apellido1" />
                            <x-input-error for="titular_apellido1" class="mt-2" />
                        </div>
                        <div class="mt-2 w-1/3">
                            <x-label :value="__('Apellido 2')" />
                            <x-input class="block mt-1 w-full" type="text" wire:model="titular_apellido2" />
                        </div>
                    </div>
                    <div class="flex w-full items-end">
                        <div class="w-1/5">
                            <x-label :value="__('Género')" />
                            <x-select class="block mt-1 w-full mayusculas" wire:model="titular_genero_id">
                                <option value="">{{ __('--') }}</option>
                                @foreach ($generos as $genero)
                                    <option value="{{ $genero->id }}">{{ $genero->nombre }}</option>
                                @endforeach
                            </x-select>
                        </div>
                        <div class="ml-2 w-2/5">
                            <x-label :value="__('Fecha de nacimiento')" />
                            <x-input class="block mt-1 w-full" type="date" wire:model="titular_fecha_nacimiento" />
                            <x-input-error for="titular_fecha_nacimiento" class="mt-2" />
                        </div>
                        <div class="ml-2 flex-1 text-right">
                            <button type="button" wire:click="cancelarNuevoTitular" class="text-sm text-gray-500 hover:text-gray-800">
                                {{ __('Cancelar') }}
                            </button>
                        </div>
                    </div>
                @elseif ($persona_comunidad_id_titular)
                    <div class="flex items-center gap-2">
                        <span class="mayusculas flex-1">{{ $titularNombreMostrado }}</span>
                        <button type="button" wire:click="quitarTitularSeleccionado" class="text-gray-500 hover:text-gray-800" title="{{ __('Quitar') }}">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                @else
                    <x-input type="text" class="block w-full" placeholder="{{ __('Buscar titular por nombre o NIF…') }}" wire:model.live="titularBusqueda" />
                    @if (! empty($titularResultados))
                        <ul class="border rounded mt-1 divide-y max-h-48 overflow-y-auto">
                            @foreach ($titularResultados as $resultado)
                                <li>
                                    <button type="button" wire:click="seleccionarTitular({{ $resultado['id'] }})"
                                        class="w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 mayusculas">
                                        {{ $resultado['texto'] }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="mt-1">
                        <button type="button" wire:click="nuevoTitular" class="text-sm text-indigo-600 hover:underline">
                            <i class="fa-solid fa-plus"></i> {{ __('No existe: dar de alta a la persona titular') }}
                        </button>
                    </div>
                    <x-input-error for="persona_comunidad_id_titular" class="mt-2" />
                @endif
            </div>
        @endif
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

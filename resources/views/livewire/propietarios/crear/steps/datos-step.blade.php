<div class="flex flex-col {{ $embebido ? 'min-h-[28rem]' : 'min-h-[calc(100vh-12rem)]' }}">
    @include('livewire.propietarios.crear.navigation')

    <div class="flex-1 space-y-4">
        @if (! $documentoComprobado)
            {{-- Paso 1: de qué persona se trata (se comprueba antes de pedir más datos) --}}
            <div class="flex items-end w-full">
                <div class="w-1/5">
                    <x-label :value="__('País')" />
                    <x-select class="block mt-1 w-full py-3 mayusculas" wire:model="documento_pais_id">
                        @foreach ($paises as $pais)
                            <option value="{{ $pais->id }}">{{ $pais->nombre }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="ml-2 w-1/5">
                    <x-label :value="__('Tipo documento')" />
                    <x-select class="block mt-1 w-full py-3 mayusculas" wire:model.live="tipo_documento_id">
                        @foreach ($tipoDocumentoIdentificativos as $tipo)
                            <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="ml-2 w-2/5">
                    <x-label :value="__('Documento Id.')" />
                    <x-input class="block mt-1 w-full mayusculas" type="text"
                        wire:model="documento_identificativo" forzar-may autofocus />
                    <x-input-error for="documento_identificativo" class="mt-2" />
                </div>
            </div>
        @elseif ($personaExistente)
            {{-- La persona ya existe (y no era propietario todavía): solo confirmar --}}
            <div class="flex items-center gap-2 border rounded p-3">
                <span class="mayusculas flex-1">
                    {{ $documento_identificativo }} —
                    {{ $nombre ?: $razon_social }} {{ $apellido1 }} {{ $apellido2 }}
                </span>
                <button type="button" wire:click="cambiarDocumento" class="text-sm text-gray-500 hover:text-gray-800">
                    {{ __('Cambiar') }}
                </button>
            </div>
        @else
            {{-- Persona nueva (alta) o edición de un propietario ya existente: formulario completo --}}
            @unless ($propietarioId)
                <div class="text-right">
                    <button type="button" wire:click="cambiarDocumento" class="text-sm text-gray-500 hover:text-gray-800">
                        {{ __('Cambiar documento') }}
                    </button>
                </div>
            @endunless

            <div class="flex w-full">
                <div class="mt-2 w-1/4">
                    <x-label :value="__('País')" />
                    <x-select class="block mt-1 w-full mayusculas" wire:model="documento_pais_id">
                        @foreach ($paises as $pais)
                            <option value="{{ $pais->id }}">{{ $pais->nombre }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="mt-2 ml-2 w-1/5">
                    <x-label :value="__('Tipo documento')" />
                    <x-select class="block mt-1 w-full mayusculas" wire:model.live="tipo_documento_id">
                        @foreach ($tipoDocumentoIdentificativos as $tipo)
                            <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="mt-2 ml-2 w-[55%]">
                    <x-label :value="__('Documento Id.')" />
                    <x-input class="block mt-1 w-full mayusculas" type="text" wire:model="documento_identificativo" />
                    <x-input-error for="documento_identificativo" class="mt-2" />
                </div>
            </div>

            @unless ($es_tipo_documento_cif)
                <div class="flex w-full">
                    <div class="mt-2 mr-4 w-1/3">
                        <x-label :value="__('Nombre')" />
                        <x-input class="block mt-1 w-full" type="text" wire:model="nombre" />
                        <x-input-error for="nombre" class="mt-2" />
                    </div>
                    <div class="mt-2 mr-4 w-1/3">
                        <x-label :value="__('Apellido 1')" />
                        <x-input class="block mt-1 w-full" type="text" wire:model="apellido1" />
                        <x-input-error for="apellido1" class="mt-2" />
                    </div>
                    <div class="mt-2 w-1/3">
                        <x-label :value="__('Apellido 2')" />
                        <x-input class="block mt-1 w-full" type="text" wire:model="apellido2" />
                        <x-input-error for="apellido2" class="mt-2" />
                    </div>
                </div>
                <div class="flex w-full">
                    <div class="mt-2 w-1/5">
                        <x-label :value="__('Género')" />
                        <x-select class="block mt-1 w-full mayusculas" wire:model="genero_id">
                            <option value="">{{ __('--') }}</option>
                            @foreach ($generos as $genero)
                                <option value="{{ $genero->id }}">{{ $genero->nombre }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error for="genero_id" class="mt-2" />
                    </div>
                    <div class="mt-2 ml-2 w-2/5">
                        <x-label :value="__('Fecha de nacimiento')" />
                        <x-input class="block mt-1 w-full" type="date" wire:model="fecha_nacimiento" />
                        <x-input-error for="fecha_nacimiento" class="mt-2" />
                    </div>
                </div>
            @else
                <div class="flex w-full">
                    <div class="mt-2 mr-4 w-3/5">
                        <x-label :value="__('Razón social')" />
                        <x-input class="block mt-1 w-full" type="text" wire:model="razon_social" />
                        <x-input-error for="razon_social" class="mt-2" />
                    </div>
                    <div class="mt-2 w-2/5">
                        <x-label :value="__('Nombre comercial')" />
                        <x-input class="block mt-1 w-full" type="text" wire:model="nombre_comercial" />
                        <x-input-error for="nombre_comercial" class="mt-2" />
                    </div>
                </div>
            @endunless
        @endif
    </div>

    <div class="flex items-center justify-between border-t pt-4 mt-4">
        <div></div>
        <div class="flex gap-2">
            <span x-data="{ shift: false }" @keydown.shift.window="shift = true" @keyup.shift.window="shift = false" x-on:blur.window="shift = false">
                <x-button type="button" tabindex="-1" wire:click="salir($event.shiftKey)"
                    x-bind:class="shift ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-500 hover:bg-gray-600'" class="text-white">
                    <i class="fa-solid" x-bind:class="shift ? 'fa-trash' : 'fa-arrow-right-from-bracket'"></i>
                    <span x-show="!shift">{{ __('Salir') }}</span>
                    <span x-show="shift" x-cloak>{{ __('Salir y eliminar borrador') }}</span>
                </x-button>
            </span>
            <x-button type="button" wire:click="submit">
                {{ $documentoComprobado ? __('Siguiente') : __('Comprobar') }}
            </x-button>
        </div>
    </div>
</div>

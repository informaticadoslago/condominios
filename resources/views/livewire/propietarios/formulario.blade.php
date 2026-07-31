<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="5xl">
    <x-slot name="title">
        {{ $formulario->propietario?->exists ? __('Modificar propietario') : __('Nuevo propietario') }}
    </x-slot>

    <x-slot name="content">
        @if (! $formulario->propietario?->exists && ! $formulario->documentoComprobado)
            {{-- Paso 1: de qué persona se trata (se comprueba antes de pedir más datos) --}}
            <div class="flex items-end w-full">
                <div class="w-1/5">
                    <x-label :value="__('País')" />
                    <x-select class="block mt-1 w-full py-3 mayusculas" wire:model="formulario.documento_pais_id">
                        @foreach ($paises as $pais)
                            <option value="{{ $pais->id }}">{{ $pais->nombre }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="ml-2 w-1/5">
                    <x-label :value="__('Tipo documento')" />
                    <x-select class="block mt-1 w-full py-3 mayusculas" wire:model="formulario.tipo_documento_id">
                        @foreach ($formulario->tipo_documento_identificativos as $tipo)
                            <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="ml-2 w-2/5">
                    <x-label :value="__('Documento Id.')" />
                    <x-input class="block mt-1 w-full mayusculas" type="text"
                        wire:model="formulario.documento_identificativo" forzar-may autofocus />
                    <x-input-error for="formulario.documento_identificativo" class="mt-2" />
                </div>
                <div class="ml-2 w-1/5">
                    <x-button type="button" wire:click="comprobarDocumento" class="btn w-full">{{ __('Comprobar') }}</x-button>
                </div>
            </div>
        @elseif (! $formulario->propietario?->exists && $formulario->personaExistente)
            {{-- La persona ya existe (y no era propietario todavía): solo confirmar --}}
            <div class="flex items-center gap-2 border rounded p-3">
                <span class="mayusculas flex-1">
                    {{ $formulario->documento_identificativo }} —
                    {{ $formulario->nombre ?: $formulario->razon_social }} {{ $formulario->apellido1 }} {{ $formulario->apellido2 }}
                </span>
                <button type="button" wire:click="cambiarDocumento" class="text-sm text-gray-500 hover:text-gray-800">
                    {{ __('Cambiar') }}
                </button>
            </div>
        @else
            {{-- Persona nueva (alta) o edición de un propietario ya existente: formulario completo --}}
            @unless ($formulario->propietario?->exists)
                <div class="text-right">
                    <button type="button" wire:click="cambiarDocumento" class="text-sm text-gray-500 hover:text-gray-800">
                        {{ __('Cambiar documento') }}
                    </button>
                </div>
            @endunless

            @include('livewire.parciales.documento-identificativo')
            @include('livewire.parciales.nombre-razon-social')

            @unless ($formulario->es_tipo_documento_cif)
                <div class="flex w-full">
                    <div class="mt-2 w-1/5">
                        <x-label for="select-propietario-genero" :value="__('Género')" />
                        <x-select id="select-propietario-genero" class="block mt-1 w-full mayusculas" name="genero"
                            wire:model.live.fill="formulario.genero_id">
                            <option value="">{{ __('--') }}</option>
                            @foreach ($generos as $genero)
                                <option value="{{ $genero->id }}" @if ($genero->id == $formulario->genero_id) selected @endif>
                                    {{ $genero->nombre }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error for="formulario.genero_id" class="mt-2" />
                    </div>
                    <div class="mt-2 ml-2 w-2/5">
                        <x-label for="input-propietario-fecha-nacimiento" :value="__('Fecha de nacimiento')" />
                        <x-input id="input-propietario-fecha-nacimiento" class="block mt-1 w-full" type="date"
                            name="fecha-nacimiento" wire:model="formulario.fecha_nacimiento" />
                        <x-input-error for="formulario.fecha_nacimiento" class="mt-2" />
                    </div>
                </div>
            @endunless
        @endif
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        @if ($formulario->propietario?->exists || $formulario->documentoComprobado)
            <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
                title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
        @endif
    </x-slot>
</x-dosl.dialog-modal>

<div>
<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="4xl">
    <x-slot name="title">
        {{ $itemId ? __('Modificar miembro de la directiva') : __('Nuevo miembro de la directiva') }}
    </x-slot>

    <x-slot name="content">
        @if (! $documentoComprobado)
            {{-- Alta, primer paso: de qué persona se trata. Si ya está en la comunidad se reutiliza. --}}
            <div class="grid sm:grid-cols-4 gap-4">
                <div>
                    <x-label for="dir-pais" :value="__('País')" />
                    <x-select id="dir-pais" class="block mt-1 w-full py-3 mayusculas" wire:model="documento_pais_id">
                        @foreach ($paises as $pais)
                            <option value="{{ $pais->id }}">{{ $pais->nombre }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error for="documento_pais_id" class="mt-2" />
                </div>
                <div>
                    <x-label for="dir-tipo-documento" :value="__('Tipo documento')" />
                    <x-select id="dir-tipo-documento" class="block mt-1 w-full py-3 mayusculas" wire:model="tipo_documento_id">
                        @foreach ($tiposDocumento as $tipo)
                            <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error for="tipo_documento_id" class="mt-2" />
                </div>
                <div class="sm:col-span-2">
                    <x-label for="dir-documento" :value="__('Documento Id.')" />
                    <x-input id="dir-documento" class="block mt-1 w-full mayusculas" type="text"
                        wire:model="documento_identificativo" wire:keydown.enter="comprobarDocumento" forzar-may autofocus />
                    <x-input-error for="documento_identificativo" class="mt-2" />
                </div>
            </div>
        @else
            @if ($persona)
                {{-- Persona que ya estaba en la comunidad (o la del directivo que se modifica) --}}
                <div class="flex items-center gap-2 border rounded p-3">
                    <span class="mayusculas flex-1">
                        {{ $persona->documento_identificativo }} — {{ $persona->nombre_completo }}
                    </span>
                    @unless ($itemId)
                        <button type="button" wire:click="cambiarDocumento" class="text-sm text-gray-500 hover:text-gray-800">
                            {{ __('Cambiar') }}
                        </button>
                    @endunless
                </div>
                @if (! $itemId && ! $persona->fecha_nacimiento)
                    {{-- Sin fecha de nacimiento no se sabe si es mayor de edad: se pide y se le guarda. --}}
                    <div class="grid sm:grid-cols-3 gap-4 mt-2">
                        <div>
                            <x-label for="dir-fecha-nacimiento" :value="__('Fecha de nacimiento')" />
                            <x-input id="dir-fecha-nacimiento" class="block mt-1 w-full" type="date" wire:model="fecha_nacimiento" />
                            <x-input-error for="fecha_nacimiento" class="mt-2" />
                        </div>
                    </div>
                @endif
            @else
                {{-- Persona nueva en la comunidad: sus datos --}}
                <div class="flex items-center gap-2 border rounded p-3">
                    <span class="mayusculas flex-1">{{ $documento_identificativo }}</span>
                    <button type="button" wire:click="cambiarDocumento" class="text-sm text-gray-500 hover:text-gray-800">
                        {{ __('Cambiar documento') }}
                    </button>
                </div>
                <x-input-error for="documento_identificativo" class="mt-2" />
                <div class="grid sm:grid-cols-3 gap-4 mt-2">
                    <div>
                        <x-label for="dir-nombre" :value="__('Nombre')" />
                        <x-input id="dir-nombre" class="block mt-1 w-full" type="text" wire:model="nombre" />
                        <x-input-error for="nombre" class="mt-2" />
                    </div>
                    <div>
                        <x-label for="dir-apellido1" :value="__('Apellido 1')" />
                        <x-input id="dir-apellido1" class="block mt-1 w-full" type="text" wire:model="apellido1" />
                        <x-input-error for="apellido1" class="mt-2" />
                    </div>
                    <div>
                        <x-label for="dir-apellido2" :value="__('Apellido 2')" />
                        <x-input id="dir-apellido2" class="block mt-1 w-full" type="text" wire:model="apellido2" />
                        <x-input-error for="apellido2" class="mt-2" />
                    </div>
                    <div>
                        <x-label for="dir-genero" :value="__('Género')" />
                        <x-select id="dir-genero" class="block mt-1 w-full mayusculas" wire:model="genero_id">
                            <option value="">{{ __('--') }}</option>
                            @foreach ($generos as $genero)
                                <option value="{{ $genero->id }}">{{ $genero->nombre }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error for="genero_id" class="mt-2" />
                    </div>
                    <div>
                        <x-label for="dir-fecha-nacimiento" :value="__('Fecha de nacimiento')" />
                        <x-input id="dir-fecha-nacimiento" class="block mt-1 w-full" type="date" wire:model="fecha_nacimiento" />
                        <x-input-error for="fecha_nacimiento" class="mt-2" />
                    </div>
                </div>
            @endif

            <div class="grid sm:grid-cols-3 gap-4 mt-4 pt-4 border-t">
                <div>
                    <x-label for="dir-puesto" :value="__('Puesto')" />
                    <x-select id="dir-puesto" class="block mt-1 w-full py-3" wire:model="tipo_directivo_comunidad_id">
                        <option value="">{{ __('--') }}</option>
                        @foreach ($puestos as $id => $descripcion)
                            <option value="{{ $id }}">{{ $descripcion }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error for="tipo_directivo_comunidad_id" class="mt-2" />
                </div>
                <div>
                    <x-label for="dir-fecha-inicio" :value="__('Fecha de inicio')" />
                    <x-input id="dir-fecha-inicio" class="block mt-1 w-full" type="date" wire:model="fecha_inicio" />
                    <x-input-error for="fecha_inicio" class="mt-2" />
                </div>
                <div>
                    <x-label for="dir-fecha-fin" :value="__('Fecha de fin')" />
                    <x-input id="dir-fecha-fin" class="block mt-1 w-full" type="date" wire:model="fecha_fin" />
                    <x-input-error for="fecha_fin" class="mt-2" />
                </div>
            </div>
        @endif
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        {{-- Mismo botón para los dos pasos del alta: comprueba el documento y, ya comprobado, guarda. --}}
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ $documentoComprobado ? __('Guardar') : __('Comprobar') }}">
            {{ $documentoComprobado ? __('Guardar') : __('Comprobar') }}
        </button>
    </x-slot>
</x-dosl.dialog-modal>
</div>

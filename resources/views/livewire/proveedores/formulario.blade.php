<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="5xl">
    <x-slot name="title">
        {{ $formulario->proveedor?->exists ? __('Modificar proveedor') : __('Nuevo proveedor') }}
    </x-slot>

    <x-slot name="content">
        @include('livewire.parciales.documento-identificativo')
        @include('livewire.parciales.nombre-razon-social')

        @unless ($formulario->es_tipo_documento_cif)
            <div class="flex w-full">
                <div class="mt-2 w-1/5">
                    <x-label for="select-proveedor-genero" :value="__('Género')" />
                    <x-select id="select-proveedor-genero" class="block mt-1 w-full mayusculas" name="genero"
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
                    <x-label for="input-proveedor-fecha-nacimiento" :value="__('Fecha de nacimiento')" />
                    <x-input id="input-proveedor-fecha-nacimiento" class="block mt-1 w-full" type="date"
                        name="fecha-nacimiento" wire:model="formulario.fecha_nacimiento" />
                    <x-input-error for="formulario.fecha_nacimiento" class="mt-2" />
                </div>
            </div>
        @endunless
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Guardar') }}">{{ __('Guardar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

<div class="flex mb-1">
    <div class="mt-4 mr-4">
        <x-label for="input-crear-tipo-direccion-domicilio" :value="__('Tipo direccion')" />
        <x-input id="crear-tipo-direccion-domicilio" class="block mt-1 w-full transparente" type="text"
            name="text-domicilio" readonly tabindex="-1" value="{{ __('Domicilio') }}" />
        <x-input id="input-crear-tipo-direccion-domicilio" class="hidden" type="text" name="domicilio"
            wire:model="formulario_domicilio.tipo_direccion_id" />
    </div>

    <div class="mt-4">
        <x-label for="select-crear-direccion-domicilio-pais" :value="__('Pais')" />
        <select id="select-crear-direccion-domicilio-pais" class="block mt-1 w-40 py-3" name="direccion-domicilio-pais"
            wire:model.live.fill="formulario_domicilio.pais_id" tabindex="-1">
            @foreach ($paises as $pais)
                <option value="{{ $pais->id }}">{{ $pais->nombre }}</option>
            @endforeach
        </select>
        <x-input-error for="formulario_domicilio.pais_id" class="mt-2" />
    </div>
</div>
@if ($formulario_domicilio->pais_is_spain())
    <div class="flex mb-1 w-full">
        <div class="mt-4 mr-4 w-2/5">
            <x-label for="input-crear-codigopostal-direccion-domicilio" :value="__('Código postal')" />
            <x-input id="input-crear-codigopostal-direccion-domicilio" class="block mt-1 w-full" type="text"
                name="codigopostal-direccion-domicilio" wire:model="formulario_domicilio.codigo_postal" />
            <x-input-error for="formulario_domicilio.codigo_postal" class="mt-2" />
        </div>
        <div class="mt-4 mr-4 w-2/5">
            <x-label for="select-crear-provincia-direccion-domicilio" :value="__('Provincia')" />
            <select id="select-crear-provincia-direccion-domicilio" class="block mt-1 w-full py-3"
                name="provincia-direccion-domicilio" wire:model.live.fill="formulario_domicilio.provincia_id">
                @foreach ($provincias as $provincia)
                    <option value="{{ $provincia->id }}">{{ $provincia->nombre }}</option>
                @endforeach
            </select>
            <x-input-error for="formulario_domicilio.provincia_id" class="mt-2" />
        </div>
        <div class="mt-4 w-full">
            <x-label for="select-crear-municipio-direccion-domicilio" :value="__('Localidad:')" />
            <select id="select-crear-municipio-direccion-domicilio" class="block mt-1 w-full py-3"
                name="municipio-direccion-domicilio" wire:model.live.fill="formulario_domicilio.municipio_id">
                @foreach ($formulario_domicilio->municipios as $municipio)
                    <option value="{{ $municipio->id }}">{{ $municipio->nombre }}</option>
                @endforeach
            </select>
            <x-input-error for="formulario_domicilio.municipio_id" class="mt-2" />
        </div>
    </div>

    <div class="flex mb-1 w-full">
        <div class="mt-4 mr-4 w-full">
            <x-label for="input-crear-direccion1-direccion-domicilio" :value="__('Direccion')" />
            <x-input id="input-crear-direccion1-direccion-domicilio" class="block mt-1 w-full" type="text"
                name="direccion1-direccion-domicilio" wire:model="formulario_domicilio.direccion1" />
            <x-input-error for="formulario_domicilio.direccion1" class="mt-2" />
        </div>
        <div class="mt-4 mr-4 w-12">
            <x-label for="input-crear-numero-direccion-domicilio" :value="__('Número')" />
            <x-input id="input-crear-numero-direccion-domicilio" class="block mt-1 w-full" type="text"
                name="numero-direccion-domicilio" wire:model="formulario_domicilio.numero" />
            <x-input-error for="formulario_domicilio.numero" class="mt-2" />
        </div>
        <div class="mt-4 mr-4 w-10">
            <x-label for="input-crear-portal-direccion-domicilio" :value="__('Portal')" />
            <x-input id="input-crear-portal-direccion-domicilio" class="block mt-1 w-full" type="text"
                name="portal-direccion-domicilio" wire:model="formulario_domicilio.portal" />
            <x-input-error for="formulario_domicilio.portal" class="mt-2" />
        </div>

        <div class="mt-4 mr-4 w-12">
            <x-label for="input-crear-piso-direccion-domicilio" :value="__('Piso')" />
            <x-input id="input-crear-piso-direccion-domicilio" class="block mt-1 w-full" type="text"
                name="piso-direccion-domicilio" wire:model="formulario_domicilio.piso" />
            <x-input-error for="formulario_domicilio.piso" class="mt-2" />
        </div>
        <div class="mt-4 w-15">
            <x-label for="input-crear-puerta-direccion-domicilio" :value="__('Puerta')" />
            <x-input id="input-crear-puerta-direccion-domicilio" class="block mt-1 w-full" type="text"
                name="puerta-direccion-domicilio" wire:model="formulario_domicilio.puerta" />
            <x-input-error for="formulario_domicilio.puerta" class="mt-2" />
        </div>
    </div>
@else
    <div class="mt-4 mr-4 w-full">
        <x-label for="input-crear-direccion1-direccion-domicilio" :value="__('Direccion')" />
        <x-input id="input-crear-direccion1-direccion-domicilio" class="block mt-1 w-full" type="text"
            name="direccion1-direccion-domicilio" wire:model="formulario_domicilio.direccion1" />
        <x-input-error for="formulario_domicilio.direccion1" class="mt-2" />
    </div>

    <div class="flex mb-1 w-full">
        <div class="mt-4 mr-4 w-2/5">
            <x-label for="input-crear-codigopostal-noes-direccion-domicilio" :value="__('Código postal')" />
            <x-input id="input-crear-codigopostal-noes-direccion-domicilio" class="block mt-1 w-full"
                type="text" name="codigopostal-direccion-domicilio"
                wire:model="formulario_domicilio.codigo_postal" />
            <x-input-error for="formulario_domicilio.codigo_postal" class="mt-2" />
        </div>
        <div class="mt-4 mr-4 w-2/5">
            <x-label for="input-crear-municipio-direccion-domicilio" :value="__('Municipio')" />
            <x-input id="input-crear-municipio-direccion-domicilio" class="block mt-1 w-full" type="text"
                name="municipio-direccion-domicilio" wire:model="formulario_domicilio.municipio" />
            <x-input-error for="formulario_domicilio.municipio" class="mt-2" />
        </div>

        <div class="mt-4 w-full">
            <x-label for="input-crear-provincia-direccion-domicilio" :value="__('Provincia')" />
            <x-input id="input-crear-provincia-direccion-domicilio" class="block mt-1 w-full" type="text"
                name="provincia-direccion-domicilio" wire:model="formulario_domicilio.provincia" />
            <x-input-error for="formulario_domicilio.provincia" class="mt-2" />
        </div>
    </div>

@endif


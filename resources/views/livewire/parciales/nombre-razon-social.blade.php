@if (!$formulario->es_tipo_documento_cif)
<div class="flex w-full">
    <div class="mt-2 mr-4 w-1/3">
        <x-label for="input-crear-nombre" :value="__('Nombre')" />
        <x-input id="input-crear-nombre" class="block mt-1 w-full" type="text" name="nombre"
            wire:model="formulario.nombre" />
        <x-input-error for="formulario.nombre" class="mt-2" />
        <div wire:dirty wire:target="formulario.nombre">Sin juardar</div>
    </div>

    <div class="mt-2 mr-4  w-1/3">
        <x-label for="input-crear-apellido1" :value="__('Apellido 1')" />
        <x-input id="input-crear-apellido1" class="block mt-1 w-full" type="text" name="apellido1"
            wire:model="formulario.apellido1" />
        <x-input-error for="formulario.apellido1" class="mt-2" />
    </div>
    <div class="mt-2 w-1/3">
        <x-label for="input-crear-apellido2" :value="__('Apellido 2')" />
        <x-input id="input-crear-apellido2" class="block mt-1 w-full" type="text" name="apellido2"
            wire:model="formulario.apellido2" />
        <x-input-error for="formulario.apellido2" class="mt-2" />
    </div>
</div>
@else
<div class="flex mb-2 w-full">
    <div class="mt-2 mr-4 w-3/5">
        <x-label for="input-crear-razonsocial" :value="__('Razón social')" />
        <x-input id="input-crear-razonsocial" class="block mt-1 w-full" type="text" name="razon_social"
            wire:model="formulario.razon_social" />
        <x-input-error for="formulario.razon_social" class="mt-2" />
        <div wire:dirty wire:target="formulario.razon_social">Sin guardar</div>
    </div>

    <div class="mt-2 w-2/5">
        <x-label for="input-crear-nombrecomercial" :value="__('Nombre comercial')" />
        <x-input id="input-crear-nombrecomercial" class="block mt-1 w-full" type="text"
            name="nombre_comercial" wire:model="formulario.nombre_comercial" />
        <x-input-error for="formulario.nombre_comercial" class="mt-2" />
    </div>
</div>
@endif

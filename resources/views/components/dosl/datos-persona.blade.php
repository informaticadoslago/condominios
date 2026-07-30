@props(['persona' => null, 'generos' => []])

@php
    $esJuridica = optional($persona?->tipoDocumentoIdentificativo)->tipo == \App\Models\TipoDocumentoIdentificativo::TIPO_JURIDICA;
@endphp

{{-- Bloque "Datos de la persona": documento (solo lectura) + resto editable
     (wire:model contra el componente padre). Física o jurídica según el documento.
     El padre debe tener las props: nombre/apellido1/apellido2/genero_id/fecha_nacimiento
     (física) o razon_social/nombre_comercial (jurídica), y pasar :generos. --}}
<strong>{{ __('Datos de la persona') }}:</strong>

<x-dosl.documento-persona :persona="$persona" />

@if ($esJuridica)
    <div class="flex w-full gap-4">
        <div class="mt-2 w-1/2">
            <x-label :value="__('Razón social')" />
            <x-input class="block mt-1 w-full mayusculas" type="text" wire:model="razon_social" />
            <x-input-error for="razon_social" class="mt-2" />
        </div>
        <div class="mt-2 w-1/2">
            <x-label :value="__('Nombre comercial')" />
            <x-input class="block mt-1 w-full mayusculas" type="text" wire:model="nombre_comercial" />
            <x-input-error for="nombre_comercial" class="mt-2" />
        </div>
    </div>
    <div class="flex w-full gap-4">
        <div class="mt-2 w-1/3">
            <x-label :value="__('Fecha de creación')" />
            <x-input class="block mt-1 w-full" type="date" max="{{ date('Y-m-d') }}" wire:model="fecha_nacimiento" />
            <x-input-error for="fecha_nacimiento" class="mt-2" />
        </div>
    </div>
@else
    <div class="flex w-full gap-4">
        <div class="mt-2 w-1/3">
            <x-label :value="__('Nombre')" />
            <x-input class="block mt-1 w-full mayusculas" type="text" wire:model="nombre" />
            <x-input-error for="nombre" class="mt-2" />
        </div>
        <div class="mt-2 w-1/3">
            <x-label :value="__('Apellido 1')" />
            <x-input class="block mt-1 w-full mayusculas" type="text" wire:model="apellido1" />
            <x-input-error for="apellido1" class="mt-2" />
        </div>
        <div class="mt-2 w-1/3">
            <x-label :value="__('Apellido 2')" />
            <x-input class="block mt-1 w-full mayusculas" type="text" wire:model="apellido2" />
            <x-input-error for="apellido2" class="mt-2" />
        </div>
    </div>
    <div class="flex w-full gap-4">
        <div class="mt-2 w-1/3">
            <x-label :value="__('Género')" />
            <x-select class="block mt-1 w-full mayusculas py-3" wire:model="genero_id">
                <option value="">{{ __('--') }}</option>
                @foreach ($generos as $genero)
                    <option value="{{ $genero->id }}">{{ $genero->nombre }}</option>
                @endforeach
            </x-select>
            <x-input-error for="genero_id" class="mt-2" />
        </div>
        <div class="mt-2 w-1/3">
            <x-label :value="__('Fecha de nacimiento')" />
            <x-input class="block mt-1 w-full" type="date" max="{{ date('Y-m-d') }}" wire:model="fecha_nacimiento" />
            <x-input-error for="fecha_nacimiento" class="mt-2" />
        </div>
    </div>
@endif

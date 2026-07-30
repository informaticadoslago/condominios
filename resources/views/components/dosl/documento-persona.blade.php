@props(['persona' => null])

{{-- Documento identificativo (solo lectura): país, tipo y número. La identidad
     se cambia en Personas → Editar, no desde el rol. Reutilizable en todos los edits. --}}
<div class="flex w-full items-start gap-2">
    <div class="mt-2 w-1/5">
        <x-label :value="__('País')" />
        <x-input class="block mt-1 w-full mayusculas" type="text"
            value="{{ $persona?->paisDocumentoIdentificativo?->nombre }}" readonly tabindex="-1" />
    </div>
    <div class="mt-2 w-1/5">
        <x-label :value="__('Tipo documento')" />
        <x-input class="block mt-1 w-full mayusculas" type="text"
            value="{{ $persona?->tipoDocumentoIdentificativo?->nombre }}" readonly tabindex="-1" />
    </div>
    <div class="mt-2 w-2/5">
        <x-label :value="__('Documento Id.')" />
        <x-input class="block mt-1 w-full mayusculas" type="text"
            value="{{ $persona?->documento_identificativo }}" readonly tabindex="-1" />
    </div>
</div>

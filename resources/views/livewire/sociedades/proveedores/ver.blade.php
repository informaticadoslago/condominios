<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="4xl">
    <x-slot name="title">
        {{ __('Proveedor') }}
    </x-slot>

    <x-slot name="content">
        @if ($proveedor)
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <x-label :value="__('Nombre / Razón social')" />
                    <div class="mt-1 mayusculas">{{ $proveedor->persona->nombreCompleto }}</div>
                </div>
                <div>
                    <x-label :value="__('Documento')" />
                    <div class="mt-1">
                        {{ $proveedor->persona->tipoDocumentoIdentificativo->nombre ?? '' }}
                        {{ $proveedor->persona->documento_identificativo }}
                    </div>
                </div>
            </div>
        @endif
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-editar" wire:click="editar" title="{{ __('Modificar') }}">
            <i class="fa-solid fa-pen"> </i>{{ __('Editar') }}
        </button>
    </x-slot>
</x-dosl.dialog-modal>

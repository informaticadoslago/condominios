<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="lg">
    <x-slot name="title">
        {{ __('Pagar factura') }}
    </x-slot>

    <x-slot name="subtitulo">
        <span class="mayusculas">{{ $proveedor }}</span> —
        {{ __('pendiente') }}: {{ number_format($pendiente, 2, ',', '.') }} €
    </x-slot>

    <x-slot name="content">
        <div class="flex w-full gap-2">
            <div class="w-1/2">
                <x-label for="input-pago-fecha" :value="__('Fecha del pago')" />
                <x-input id="input-pago-fecha" class="block mt-1 w-full" type="date" wire:model="fecha" />
                <x-input-error for="fecha" class="mt-2" />
            </div>
            <div class="w-1/2">
                {{-- Viene con el pendiente puesto: lo normal es pagar la factura entera, y
                     quien pague a cuenta lo cambia aquí. --}}
                <x-label for="input-pago-importe" :value="__('Importe')" />
                <x-input id="input-pago-importe" class="block mt-1 w-full" type="number" step="0.01"
                    wire:model="importe" />
                <x-input-error for="importe" class="mt-2" />
            </div>
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="guardar"
            title="{{ __('Pagar') }}">{{ __('Pagar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

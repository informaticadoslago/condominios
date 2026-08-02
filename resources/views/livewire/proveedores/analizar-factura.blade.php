<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="2xl">
    <x-slot name="title">
        {{ __('Analizar factura') }}
    </x-slot>

    <x-slot name="content">
        <label for="input-facturas"
            x-data="{ arrastrando: false }"
            x-on:dragover.prevent="arrastrando = true"
            x-on:dragleave.prevent="arrastrando = false"
            x-on:drop.prevent="arrastrando = false; console.log('drop: ficheros=', $event.dataTransfer.files.length, 'tipos=', $event.dataTransfer.types); $wire.uploadMultiple('facturas', $event.dataTransfer.files, () => console.log('uploadMultiple: terminado'), (error) => console.error('uploadMultiple: error', error))"
            :class="arrastrando ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' : 'border-gray-300 dark:border-gray-600'"
            class="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed rounded-lg cursor-pointer transition-colors">
            <i class="fa-solid fa-file-pdf text-3xl text-gray-400 mb-2"></i>
            <span class="text-sm text-gray-500 dark:text-gray-400 text-center px-4">
                {{ __('Arrastra aquí las facturas en PDF o haz clic para buscarlas') }}
            </span>
            <input type="file" id="input-facturas" wire:model="facturas" multiple
                accept="application/pdf" class="hidden" />
        </label>

        <div wire:loading wire:target="facturas" class="text-sm text-gray-500 mt-2">
            {{ __('Cargando...') }}
        </div>

        <x-input-error for="facturas" class="mt-2" />
        <x-input-error for="facturas.*" class="mt-2" />

        @if (count($facturas))
            <ul class="mt-4 divide-y border rounded">
                @foreach ($facturas as $index => $factura)
                    <li class="flex items-center justify-between px-3 py-2 text-sm" wire:key="factura-{{ $index }}">
                        <span class="truncate">
                            <i class="fa-solid fa-file-pdf text-red-500 mr-1"></i>
                            {{ $factura->getClientOriginalName() }}
                        </span>
                        <button type="button" wire:click="quitar({{ $index }})" class="text-gray-400 hover:text-red-600">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        @if (count($facturas))
            <button type="button" class="btn btn-guardar px-2" wire:click="procesar" wire:loading.attr="disabled"
                wire:target="procesar" title="{{ __('Procesar') }}">
                <span wire:loading.remove wire:target="procesar">{{ __('Procesar') }}</span>
                <span wire:loading wire:target="procesar">{{ __('Procesando...') }}</span>
            </button>
        @endif
    </x-slot>
</x-dosl.dialog-modal>

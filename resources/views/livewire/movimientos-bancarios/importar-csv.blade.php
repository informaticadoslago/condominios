<x-dosl.dialog-modal wire:model.live="abrir" class="backdrop-blur" maxWidth="2xl">
    <x-slot name="title">
        {{ __('Importar movimientos bancarios') }}
    </x-slot>

    <x-slot name="content">
        <div>
            <x-label :value="__('Extracto de movimientos (CSV o Q43/Norma 43)')" />
            <label for="mb-fichero"
                x-data="{ arrastrando: false }"
                x-on:dragover.prevent="arrastrando = true"
                x-on:dragleave.prevent="arrastrando = false"
                x-on:drop.prevent="arrastrando = false; $wire.upload('fichero', $event.dataTransfer.files[0])"
                :class="arrastrando ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' : 'border-gray-300 dark:border-gray-600'"
                class="flex flex-col items-center justify-center w-full h-32 mt-1 border-2 border-dashed rounded-lg cursor-pointer transition-colors">
                <i class="fa-solid fa-file-csv text-3xl text-gray-400 mb-2"></i>
                <span class="text-sm text-gray-500 dark:text-gray-400 text-center px-4">
                    @if ($fichero)
                        {{ $fichero->getClientOriginalName() }}
                    @else
                        {{ __('Arrastra aquí el extracto o haz clic para buscarlo') }}
                    @endif
                </span>
                <input type="file" id="mb-fichero" wire:model="fichero" accept=".csv,.txt,.q43" class="hidden" />
            </label>

            <div wire:loading wire:target="fichero" class="text-sm text-gray-500 mt-2">
                {{ __('Cargando...') }}
            </div>

            <x-input-error for="fichero" class="mt-2" />
            @if ($error)
                <p class="mt-2 text-sm text-red-600">{{ $error }}</p>
            @endif

            <p class="mt-2 text-xs text-gray-500">
                {{ __('Se importa todo el fichero: lo que ya estuviera dado de alta se salta solo.') }}
            </p>
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar />
        <button type="button" class="btn btn-guardar px-2" wire:click="procesar" wire:loading.attr="disabled"
            title="{{ __('Importar') }}">{{ __('Importar') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

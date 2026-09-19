<x-dosl.dialog-modal wire:model.live="abrir" maxWidth="lg">
    <x-slot name="title">
        {{ __('Importar horario') }}
    </x-slot>

    <x-slot name="content">
        <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
            {{ __('Selecciona el ZIP generado por la exportación de horario. No se crean tablas nuevas: se insertan datos en las existentes.') }}
        </p>

        <label for="zip-importacion-horario"
            x-data="{ arrastrando: false }"
            x-on:dragover.prevent="arrastrando = true"
            x-on:dragleave.prevent="arrastrando = false"
            x-on:drop.prevent="arrastrando = false; $wire.upload('zip', $event.dataTransfer.files[0])"
            :class="arrastrando ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' : 'border-gray-300 dark:border-gray-600'"
            class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed rounded-lg cursor-pointer transition-colors">
            <i class="fa-solid fa-file-zipper text-3xl text-gray-400 mb-2"></i>
            <span class="text-sm text-gray-500 dark:text-gray-400 text-center px-4">
                @if ($zip)
                    {{ $zip->getClientOriginalName() }}
                @else
                    {{ __('Arrastra aquí el .zip de exportación o haz clic para buscarlo') }}
                @endif
            </span>
            <input type="file" id="zip-importacion-horario" wire:model="zip" accept=".zip" class="hidden" />
        </label>

        <div wire:loading wire:target="zip" class="text-sm text-gray-500 mt-2">
            {{ __('Cargando...') }}
        </div>

        <x-input-error for="zip" class="mt-2" />
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar accion="cerrar" />
        <button type="button" class="btn btn-guardar px-2" wire:click="importar" wire:loading.attr="disabled"
            title="{{ __('Importar ZIP') }}">{{ __('Importar ZIP') }}</button>
    </x-slot>
</x-dosl.dialog-modal>

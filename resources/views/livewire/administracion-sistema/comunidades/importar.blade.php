<x-botonera-page>
    <x-slot name="title">
        {{ __('Importar comunidad') }}
    </x-slot>
    <x-slot name="subtitulo">
        {{ __('Sube el ZIP exportado y el sistema lo procesará en segundo plano') }}
    </x-slot>

    <x-slot name="botonera">
        <x-ui-button type="button" class="btn btn-nuevo" wire:click="importar" wire:loading.attr="disabled"
            title="{{ __('Importar ZIP') }}" :disabled="! $zip">
            <i class="fa-solid fa-file-import"></i> {{ __('Importar ZIP') }}
        </x-ui-button>
    </x-slot>

    <x-slot name="content">
        <x-dosl.tabla>
            <div class="max-w-3xl mx-auto py-8 px-6">
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                    {{ __('Selecciona el ZIP generado por la exportación de comunidad. No se crean tablas nuevas: se insertan datos en las existentes.') }}
                </p>

                <label for="zip-importacion-comunidad"
                    class="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed rounded-lg cursor-pointer transition-colors border-gray-300 dark:border-gray-600">
                    <i class="fa-solid fa-file-zipper text-3xl text-gray-400 mb-2"></i>
                    <span class="text-sm text-gray-500 dark:text-gray-400 text-center px-4">
                        {{ __('Haz clic para elegir el .zip de exportación') }}
                    </span>
                    <input type="file" id="zip-importacion-comunidad" accept=".zip" wire:model="zip" class="hidden" />
                </label>

                <x-input-error for="zip" class="mt-2" />

                @if ($zip)
                    <div class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                        {{ __('Fichero seleccionado') }}: {{ $zip->getClientOriginalName() }}
                    </div>
                @endif

                <div class="mt-6 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('El trabajo se encola en la cola por defecto. Cuando termine, revisa el log si algo falla.') }}
                </div>
            </div>
        </x-dosl.tabla>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar accion="cerrar" />
    </x-slot>
</x-botonera-page>
<x-dosl.dialog-modal wire:model.live="abrirAcercaDe" class="backdrop-blur" maxWidth="sm">
    <x-slot name="title">
        {{ __('Acerca de…') }}
    </x-slot>

    <x-slot name="content">
        <div class="text-center py-2">
            <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ config('app.name') }}</div>
            <div class="mt-1">{{ $version }}</div>
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-dosl.boton-cerrar accion="close" />
    </x-slot>
</x-dosl.dialog-modal>

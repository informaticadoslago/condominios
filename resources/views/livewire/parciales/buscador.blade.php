{{-- Caja de búsqueda de una lista, con su propia X para limpiarla.
     Uso: @include('livewire.parciales.buscador', ['placeholder' => 'Nombre o documento']) --}}
<x-label class="font-semibold text-base">{{ __('Buscar') }}: </x-label>

<div class="relative flex-1 mx-4">
    <x-input class="w-full pr-9 disabled:opacity-50 disabled:cursor-not-allowed" placeholder="{{ $placeholder ?? '' }}"
        wire:model.live="search" :disabled="$verSoloSeleccionados ?? false" />

    @if (filled($search))
        <button type="button" wire:click="limpiarBusqueda" title="{{ __('Limpiar búsqueda') }}"
            class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
            <i class="fa-solid fa-xmark"></i>
        </button>
    @endif
</div>

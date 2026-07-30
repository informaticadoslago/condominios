@if ($this->entradaMenu && !$this->accesoDirectoGuardado)
    <button type="button" wire:click="crearAccesoDirecto"
        class="flex items-center gap-1 text-xs font-medium text-gray-300 hover:text-white dark:text-gray-600 dark:hover:text-gray-900"
        title="{{ __('Crear acceso directo en el inicio') }}">
        <i class="fa-regular fa-star"></i>{{ __('Acceso directo') }}
    </button>
@endif

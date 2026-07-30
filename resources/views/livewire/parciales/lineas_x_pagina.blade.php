<div class="flex items-center mr-3">
    <span class="flex items-center">{{ __('Mostrar') }}</span>
    <select wire:model.live="lineasXPagina" class="mx-2">
        <option value="10">10</option>
        <option value="25">25</option>
        <option value="50">50</option>
        <option value="100">100</option>
    </select>
    <span>{{ __('entradas') }}</span>
</div>

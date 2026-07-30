@props([
    'source' => 'buscarCargos', // método Livewire del padre: buscarCargos(string $q, int $limit): array
    'limit' => 8, // nº máximo de resultados que pedirá
    'items', // nombre de la propiedad del padre con la lista (p.ej. "cargos")
    'placeholder' => null,
    'name' => null,
])

@php
    // Nombre de la propiedad enlazada con wire:model (ej.: "cargo")
    $wireModel = optional($attributes->wire('model'))->value();

    // Leer la lista de strings del Livewire padre (p.ej. $this->cargos)
    $lista = [];
    try {
        if (isset($__livewire)) {
            $lista = (array) ($__livewire->{$items} ?? []);
        }
    } catch (\Throwable $e) {
        $lista = [];
    }
@endphp

<div class="relative w-full">
    <input type="text" name="{{ $name }}" placeholder="{{ $placeholder }}"
        {{ $attributes->whereStartsWith('wire:model') }}
        wire:input.debounce.200ms="{{ $source }}($event.target.value, {{ (int) $limit }})"
        wire:keydown.escape.prevent="$set('{{ $items }}', [])"
        wire:keydown.enter.prevent="$set('{{ $items }}', [])"
        wire:keydown.tab ="$set('{{ $items }}', [])"         
        wire:blur ="$set('{{ $items }}', [])"         
        autocomplete="off"
        class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring focus:ring-indigo-200" />


    @if (!empty($lista))
        <div class="absolute z-50 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow">
            @foreach ($lista as $opcion)
                <button type="button"
                    @if ($wireModel) {{-- Usar mousedown/touchstart para ganar a blur --}}
                        wire:mousedown.prevent="$set('{{ $wireModel }}', @js($opcion)); $set('{{ $items }}', [])"
                        wire:touchstart.prevent="$set('{{ $wireModel }}', @js($opcion)); $set('{{ $items }}', [])" @endif
                    class="block w-full px-3 py-2 text-left text-sm hover:bg-gray-100"
                    role="option">{{ $opcion }}</button>
            @endforeach
        </div>
    @endif
</div>

@props([
    'id' => null,
    'type' => 'text',
    'name' => null,
    'wireModel' => null,
    'min' => null,
    'max' => null,
])

<div class="flex items-center w-full space-x-1">
    {{-- Botón restar --}}
    <button type="button"
            class="px-3 py-1 bg-gray-200 hover:bg-gray-300 disabled:opacity-50"
            @if($wireModel)
                wire:click="$set('{{ $wireModel }}', max({{ $min ?? 0 }}, {{ $wireModel }} - 1))"
            @endif>
        -
    </button>

    {{-- Input numérico --}}
    <flux:input
        {{ $attributes->merge([
            'id' => $id,
            'class' => 'block mt-1 w-full text-center',
            'type' => $type,
            'name' => $name,
        ]) }}
        @if($wireModel) wire:model="{{ $wireModel }}" @endif
        @if($min) min="{{ $min }}" @endif
        @if($max) max="{{ $max }}" @endif
    />

    {{-- Botón sumar --}}
    <button type="button"
            class="px-3 py-1 bg-gray-200 hover:bg-gray-300 disabled:opacity-50"
            @if($wireModel)
                wire:click="$set('{{ $wireModel }}', min({{ $max ?? 999999 }}, {{ $wireModel }} + 1))"
            @endif>
        +
    </button>
</div>

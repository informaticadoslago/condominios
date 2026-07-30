@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-white dark:bg-gray-700', 'dropdownClasses' => ''])

@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right',
    'top' => 'origin-top',
    'none', 'false' => '',
    default => 'ltr:origin-top-right rtl:origin-top-left',
};

$anchor = match ($align) {
    'left' => 'x-anchor.bottom-start.offset.8',
    'top' => 'x-anchor.top.offset.8',
    default => 'x-anchor.bottom-end.offset.8',
};

$width = match ($width) {
    '48' => 'w-48',
    '60' => 'w-60',
    default => 'w-48',
};
@endphp

<div class="relative" x-data="{ open: false }" @click.away="open = false" @close.stop="open = false">
    <div x-ref="trigger" @click="open = ! open">
        {{ $trigger }}
    </div>

    {{-- El panel se monta en <body> (x-teleport) y se posiciona anclado al botón
         (x-anchor): dentro de contenedores con overflow (las tablas con
         overflow-x-auto) el panel quedaba recortado. --}}
    <template x-teleport="body">
        <div x-show="open"
                {{ $anchor }}="$refs.trigger"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="transform opacity-0 scale-95"
                x-transition:enter-end="transform opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="transform opacity-100 scale-100"
                x-transition:leave-end="transform opacity-0 scale-95"
                class="absolute z-50 {{ $width }} rounded-md shadow-lg {{ $alignmentClasses }} {{ $dropdownClasses }}"
                style="display: none;"
                @click="open = false">
            <div class="rounded-md ring-1 ring-black ring-opacity-5 {{ $contentClasses }}">
                {{ $content }}
            </div>
        </div>
    </template>
</div>

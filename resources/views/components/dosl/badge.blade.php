@props(['color' => 'zafiro'])

<span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium pildora-{{ $color }}">
    {{ $slot }}
</span>
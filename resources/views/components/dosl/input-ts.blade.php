@props([
    'type' => 'text',
    'value' => '',
    'placeholder' => '',
    'disabled' => false,
])

<input
    type="{{ $type }}"
    value="{{ $value }}"
    placeholder="{{ $placeholder }}"
    @if($disabled) disabled @endif
    {{ $attributes->merge([
        'class' => 'px-2 py-1 text-lg rounded-lg border border-border bg-background text-foreground
            focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 focus:ring-offset-accent-foreground
            hover:border-border-hover disabled:bg-background-disabled disabled:text-foreground-disabled disabled:cursor-not-allowed w-full'
    ]) }}
/>
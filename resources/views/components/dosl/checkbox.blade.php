@props([
    'colorfondo' => 'verdedosl-500', // sin "text-" ni "focus:ring-" aquí
    'desactivado' => false,    
])

@php
    $colorText = "text-{$colorfondo}";
    $colorRing = "focus:ring-{$colorfondo}";
@endphp

<input type="checkbox"    
    {!! $attributes->merge([
        'class' => "rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 shadow-sm $colorText $colorRing dark:focus:ring-{$colorfondo} dark:focus:ring-offset-gray-800"
    ]) !!}     
    @if($desactivado) onclick="return false;" @endif />

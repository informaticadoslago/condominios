@props(['disabled' => false, 'forzarMay' => false, 'forzarMin' => false])

@php
    // Transforma el valor tecleado en el propio input. Se usa `oninput` nativo
    // porque se ejecuta antes que el listener de wire:model, así Livewire
    // recibe ya el valor en mayúsculas/minúsculas.
    // El aspecto visual se consigue con la clase `mayusculas` allí donde se use.
    $forzar = $forzarMay ? 'this.value=this.value.toUpperCase()'
        : ($forzarMin ? 'this.value=this.value.toLowerCase()' : null);
@endphp

<input {{ $disabled ? 'disabled' : '' }} @if ($forzar) oninput="{{ $forzar }}" @endif
    {!! $attributes->merge([
        'class' =>
            'border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm  h-14 px-5 text-xl',
    ]) !!}>

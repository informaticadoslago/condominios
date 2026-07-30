@props(['readonly' => false, 'disabled' => true,'nomayusculas'=>false, 'shadow'=>false])

<input {{ $readonly ? 'readonly' : '' }} {{ $disabled ? 'disabled' : '' }} tabindex="-1" 
style="background: transparent" {!! $attributes->merge([
    'class' =>
        'border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600   h-14 px-5 text-xl',
]) !!}>

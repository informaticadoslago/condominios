@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-normal text-sm text-gray-900 dark:text-gray-300']) }}>
    <p {{ $attributes->merge(['class' => 'block font-normal text-sm text-black dark:text-gray-300']) }}>{{ $value ?? $slot }}</p>
</label> 

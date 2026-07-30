@props([
    'title' => 'Abrir / Cerrar',
    'open' => 1, // 1 = desplegado, 0 = plegado
])

<details class="border rounded bg-gray-50 dark:bg-gray-800" {{ $open == 1 ? 'open' : '' }}>
    <summary
        class="cursor-pointer select-none px-2 py-1 rounded font-semibold
               bg-gray-100 text-gray-800 hover:bg-gray-200
               dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600">
        {{ $title }}
    </summary>

    <div class="mt-2">
        {{ $slot }}
    </div>
</details>

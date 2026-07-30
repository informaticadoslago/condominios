@props(['botonera' => null, 'footer' => null, 'justifycenter' => true])
<div>
    {{-- Header --}}
    <div class="sticky top-0 w-full px-6 py-1 bg-gray-800 dark:bg-gray-50 text-start" style="height: 72px">
        <div class="relative w-full">
            <div class="absolute left-0">
                <h3 class="text-gray-50 dark:text-gray-900 text-xl font-bold sm:text-2xl">
                    {{ $title }}
                </h3>
                @isset($subtitulo)
                    <x-dosl.tabla-descripcion class="text-gray-300 dark:text-gray-700">
                        {{ $subtitulo }}
                    </x-dosl.tabla-descripcion>
                @endisset
            </div>
            <div class="absolute right-0 py-2 flex flex-col items-end gap-1">
                {{ $botonera }}
            </div>
        </div>
    </div>

    {{-- Content --}}
    <div class="@if ($justifycenter) flex flex-row justify-center @endif px-6 py-1 bg-gray-50 dark:bg-gray-800 text-start">
        <div class="mt-4 text-sm text-gray-600 dark:text-gray-300 w-full">
            {{ $content }}
        </div>
    </div>

    {{-- Footer --}}
    <div class="flex flex-row justify-center px-6 py-4 bg-gray-50 dark:bg-gray-800 text-end text-gray-600 dark:text-gray-300">
        {{ $footer }}
    </div>
</div>